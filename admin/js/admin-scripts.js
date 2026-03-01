/* Admin scripts for Rivian Tire Guide */
(function($) {
    'use strict';

    var efficiencyTimer = null;

    $(document).ready(function() {
        // Image preview on URL change (supports prefix-based input).
        var $imageInput = $('#image');
        var $imagePrefix = $('#image_prefix');
        if ($imageInput.length) {
            $imageInput.on('input', function() {
                var val = $(this).val().trim();
                var prefix = $imagePrefix.length ? $imagePrefix.val() : '';
                // Build full URL: if input already starts with http, use as-is; otherwise prepend prefix.
                var url = val;
                if (val && prefix && !/^https?:\/\//i.test(val)) {
                    url = prefix + val;
                }
                var $preview = $('#image-preview');
                var $container = $('#image-preview-container');

                try {
                    var parsed = new URL(url);
                } catch (e) {
                    parsed = null;
                }

                if (parsed && /^https?:$/.test(parsed.protocol) && /\.(jpg|jpeg|png|webp|gif)(\?|$)/i.test(parsed.pathname)) {
                    if ($preview.length) {
                        $preview[0].src = parsed.href;
                        if ($container.length) {
                            $container.show();
                        }
                    }
                } else {
                    if ($container.length) {
                        $container.hide();
                    }
                }
            });
        }

        // Select all checkboxes.
        $('#cb-select-all').on('change', function() {
            $('input[name="tire_ids[]"]').prop('checked', this.checked);
        });

        // Confirm bulk delete.
        $('form').on('submit', function() {
            var action = $('select[name="rtg_bulk_action"]').val();
            if (action === 'delete') {
                var checked = $('input[name="tire_ids[]"]:checked').length;
                if (checked === 0) {
                    alert('No tires selected.');
                    return false;
                }
                return confirm('Delete ' + checked + ' tire(s)? This cannot be undone.');
            }
        });

        // Dismiss custom notices.
        $('.rtg-notice-dismiss').on('click', function() {
            $(this).closest('.rtg-notice').fadeOut(200, function() {
                $(this).remove();
            });
        });

        // --- Tag suggestion clicks ---
        $(document).on('click', '.rtg-tag-suggestion', function() {
            var tag = $(this).data('tag');
            var $tagsInput = $('#tags');
            var current = $tagsInput.val().trim();
            // Parse existing tags
            var tags = current ? current.split(',').map(function(t) { return t.trim(); }).filter(function(t) { return t.length > 0; }) : [];
            // Toggle: remove if already present, add if not
            var idx = tags.indexOf(tag);
            if (idx > -1) {
                tags.splice(idx, 1);
                $(this).css({ 'background': '#f5f5f7', 'color': '#1d1d1f' });
            } else {
                tags.push(tag);
                $(this).css({ 'background': '#0071e3', 'color': '#fff' });
            }
            $tagsInput.val(tags.join(', '));
        });

        // Highlight tags that are already selected on load
        var $tagsInput = $('#tags');
        if ($tagsInput.length && $tagsInput.val().trim()) {
            var currentTags = $tagsInput.val().split(',').map(function(t) { return t.trim(); });
            $('.rtg-tag-suggestion').each(function() {
                if (currentTags.indexOf($(this).data('tag')) > -1) {
                    $(this).css({ 'background': '#0071e3', 'color': '#fff' });
                }
            });
        }

        // --- Share image generator ---
        initShareImage();

        // --- Size → Diameter auto-fill (from data attribute mapped in Settings) ---
        $('#size').on('change', function() {
            var selected = $(this).find('option:selected');
            var diameter = selected.data('diameter') || '';
            $('#diameter').val(diameter);
        });

        // --- Load index → max load auto-fill ---
        $('#load_index').on('change', function() {
            var selected = $(this).find('option:selected');
            var maxLoad = selected.data('max-load') || '';
            $('#max_load_lb').val(maxLoad);
        });

        // --- Real-time efficiency calculator on the edit form ---
        // Uses AJAX to call the canonical PHP formula (eliminates duplicate JS logic).
        if ($('#rtg-efficiency-preview').length) {
            var effFields = '#size, #weight_lb, #tread, #load_range, #speed_rating, #utqg, #category, #three_pms';

            $(effFields).on('input change', function() {
                updateEfficiencyPreview();
            });

            // Run once on load to sync.
            updateEfficiencyPreview();
        }
    });

    function updateEfficiencyPreview() {
        // Debounce to avoid excessive AJAX calls during rapid input.
        if (efficiencyTimer) {
            clearTimeout(efficiencyTimer);
        }

        efficiencyTimer = setTimeout(function() {
            // Bail if rtgAdmin is not available (missing nonce localization).
            if (typeof rtgAdmin === 'undefined') {
                return;
            }

            $.post(rtgAdmin.ajaxurl, {
                action:       'rtg_calculate_efficiency',
                nonce:        rtgAdmin.nonce,
                size:         $('#size').val() || '',
                weight_lb:    $('#weight_lb').val() || '0',
                tread:        $('#tread').val() || '',
                load_range:   $('#load_range').val() || '',
                speed_rating: $('#speed_rating').val() || '',
                utqg:         $('#utqg').val() || '',
                category:     $('#category').val() || '',
                three_pms:    $('#three_pms').val() || 'No'
            }, function(response) {
                if (response.success) {
                    var score = response.data.efficiency_score;
                    var grade = response.data.efficiency_grade;

                    var gradeClasses = {
                        A: 'rtg-grade-a', B: 'rtg-grade-b', C: 'rtg-grade-c',
                        D: 'rtg-grade-d', F: 'rtg-grade-f'
                    };

                    $('#rtg-eff-grade')
                        .removeClass('rtg-grade-a rtg-grade-b rtg-grade-c rtg-grade-d rtg-grade-f rtg-grade-none')
                        .addClass(gradeClasses[grade] || '')
                        .text(grade);
                    $('#rtg-eff-score').text(score);
                }
            });
        }, 300);
    }

    // ==========================================================================
    // Share Image Generator (Canvas-based)
    // ==========================================================================

    function initShareImage() {
        var canvas = document.getElementById('rtg-share-canvas');
        if (!canvas || typeof window.rtgShareData === 'undefined') {
            return;
        }

        // Frontend theme colors (matches rivian-tires.css :root).
        var colors = {
            bgPrimary:   '#121e2b',
            bgCard:      '#162231',
            bgDeep:      '#0c1620',
            accent:      '#fba919',
            accentHover: '#fdbe40',
            textPrimary: '#e5e7eb',
            textLight:   '#f1f5f9',
            textMuted:   '#8493a5',
            textHeading: '#ffffff',
            border:      '#1e3044',
            gradeA:      '#34c759',
            gradeB:      '#7dc734',
            gradeC:      '#facc15',
            gradeD:      '#f97316',
            gradeF:      '#b91c1c',
        };

        var data = window.rtgShareData;

        function drawImage() {
            var ctx = canvas.getContext('2d');
            var W = canvas.width;
            var H = canvas.height;

            var title    = $('#rtg-share-title').val() || 'Rivian Tire Guide';
            var subtitle = $('#rtg-share-subtitle').val() || '';
            var footer   = $('#rtg-share-footer').val() || '';

            // --- Background ---
            ctx.fillStyle = colors.bgPrimary;
            ctx.fillRect(0, 0, W, H);

            // Subtle gradient overlay at top.
            var grad = ctx.createLinearGradient(0, 0, 0, 200);
            grad.addColorStop(0, colors.bgDeep);
            grad.addColorStop(1, 'transparent');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, W, 200);

            // Accent strip at top.
            ctx.fillStyle = colors.accent;
            ctx.fillRect(0, 0, W, 4);

            // --- Title area ---
            ctx.fillStyle = colors.textHeading;
            ctx.font = 'bold 42px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText(title, 60, 40);

            if (subtitle) {
                ctx.fillStyle = colors.accent;
                ctx.font = '500 20px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.fillText(subtitle, 60, 92);
            }

            // Horizontal divider below title.
            ctx.strokeStyle = colors.border;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(60, 130);
            ctx.lineTo(W - 60, 130);
            ctx.stroke();

            // --- Stat cards (2x2 grid) ---
            var stats = [
                { label: 'Total Tires',       value: String(data.totalTires) },
                { label: 'Avg Price',          value: '$' + (data.avgPrice > 0 ? Math.round(data.avgPrice).toLocaleString() : '—') },
                { label: 'Avg Efficiency',     value: String(data.avgEfficiency) },
                { label: 'Community Reviews',  value: String(data.totalReviews) },
            ];

            var cardW = 245;
            var cardH = 120;
            var cardGap = 20;
            var gridStartX = 60;
            var gridStartY = 155;

            stats.forEach(function(stat, i) {
                var col = i % 2;
                var row = Math.floor(i / 2);
                var x = gridStartX + col * (cardW + cardGap);
                var y = gridStartY + row * (cardH + cardGap);

                // Card background.
                roundRect(ctx, x, y, cardW, cardH, 10);
                ctx.fillStyle = colors.bgCard;
                ctx.fill();
                ctx.strokeStyle = colors.border;
                ctx.lineWidth = 1;
                ctx.stroke();

                // Value.
                ctx.fillStyle = colors.accent;
                ctx.font = 'bold 36px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textBaseline = 'top';
                ctx.fillText(stat.value, x + 20, y + 20);

                // Label.
                ctx.fillStyle = colors.textMuted;
                ctx.font = '500 14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.fillText(stat.label, x + 20, y + 68);
            });

            // --- Right side: top brands or categories ---
            var rightX = 630;
            var rightY = 155;

            // "Top Brands" section.
            ctx.fillStyle = colors.textHeading;
            ctx.font = 'bold 18px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText('Top Brands', rightX, rightY);

            var brands = data.brands || [];
            var maxBrandCount = 1;
            brands.forEach(function(b) { if (b.count > maxBrandCount) maxBrandCount = b.count; });

            brands.forEach(function(brand, i) {
                var barY = rightY + 35 + i * 32;
                var barMaxW = 380;
                var barW = Math.max(40, (brand.count / maxBrandCount) * barMaxW);
                var barH = 26;

                // Bar track.
                roundRect(ctx, rightX, barY, barMaxW, barH, 4);
                ctx.fillStyle = colors.bgCard;
                ctx.fill();

                // Bar fill.
                roundRect(ctx, rightX, barY, barW, barH, 4);
                ctx.fillStyle = colors.accent;
                ctx.globalAlpha = 0.85;
                ctx.fill();
                ctx.globalAlpha = 1;

                // Brand name.
                ctx.fillStyle = colors.textHeading;
                ctx.font = '600 13px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillText(brand.name, rightX + 10, barY + barH / 2);

                // Count.
                ctx.fillStyle = colors.textMuted;
                ctx.font = '500 12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(String(brand.count), rightX + barMaxW - 8, barY + barH / 2);
                ctx.textAlign = 'left';
            });

            // "Categories" section below brands.
            var catY = rightY + 35 + Math.max(brands.length, 1) * 32 + 20;
            ctx.fillStyle = colors.textHeading;
            ctx.font = 'bold 18px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textBaseline = 'top';
            ctx.fillText('Categories', rightX, catY);

            var cats = (data.categories || []).slice(0, 4);
            var catStartY = catY + 28;
            var catMaxRow = 0;
            cats.forEach(function(cat, i) {
                var chipX = rightX + i * 130;
                // Wrap to second row if needed.
                var row = 0;
                if (i >= 3) { row = 1; chipX = rightX + (i - 3) * 130; }
                if (row > catMaxRow) catMaxRow = row;

                var tagY = catStartY + row * 34;
                var tagText = cat.name + ' (' + cat.count + ')';

                // Pill background.
                var tagW = ctx.measureText(tagText).width + 20;
                roundRect(ctx, chipX, tagY, tagW, 26, 13);
                ctx.fillStyle = colors.bgCard;
                ctx.fill();
                ctx.strokeStyle = colors.border;
                ctx.lineWidth = 1;
                ctx.stroke();

                ctx.fillStyle = colors.textPrimary;
                ctx.font = '500 12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillText(tagText, chipX + 10, tagY + 13);
            });

            // --- Top rated tire callout ---
            // Position dynamically below both the left stat cards and right categories.
            var leftBottom = gridStartY + 2 * (cardH + cardGap) - cardGap;
            var rightBottom = cats.length > 0 ? catStartY + catMaxRow * 34 + 26 : catY + 18;
            var contentBottom = Math.max(leftBottom, rightBottom);

            if (data.topTire) {
                var calloutY = contentBottom + 15;
                roundRect(ctx, 60, calloutY, W - 120, 60, 10);
                ctx.fillStyle = colors.bgCard;
                ctx.fill();
                ctx.strokeStyle = colors.accent;
                ctx.lineWidth = 1;
                ctx.stroke();

                // Star icon (drawn as text).
                ctx.fillStyle = colors.accent;
                ctx.font = '20px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillText('\u2605', 82, calloutY + 30);

                ctx.fillStyle = colors.textMuted;
                ctx.font = '500 14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.fillText('Top Rated:', 108, calloutY + 30);

                ctx.fillStyle = colors.textHeading;
                ctx.font = 'bold 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.fillText(data.topTire, 195, calloutY + 30);

                if (data.topRating) {
                    ctx.fillStyle = colors.accent;
                    ctx.font = 'bold 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                    ctx.textAlign = 'right';
                    ctx.fillText(data.topRating + ' / 5 \u2605', W - 82, calloutY + 30);
                    ctx.textAlign = 'left';
                }
            }

            // --- Footer ---
            // Footer background strip.
            ctx.fillStyle = colors.bgDeep;
            ctx.fillRect(0, H - 80, W, 80);

            // Accent line above footer.
            ctx.fillStyle = colors.border;
            ctx.fillRect(0, H - 80, W, 1);

            if (footer) {
                ctx.fillStyle = colors.textMuted;
                ctx.font = '500 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillText(footer, 60, H - 40);
            }

            // Date stamp on right.
            var now = new Date();
            var dateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            ctx.fillStyle = colors.textMuted;
            ctx.font = '400 13px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(dateStr, W - 60, H - 40);
            ctx.textAlign = 'left';
        }

        // Rounded rectangle helper.
        function roundRect(ctx, x, y, w, h, r) {
            ctx.beginPath();
            ctx.moveTo(x + r, y);
            ctx.lineTo(x + w - r, y);
            ctx.quadraticCurveTo(x + w, y, x + w, y + r);
            ctx.lineTo(x + w, y + h - r);
            ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
            ctx.lineTo(x + r, y + h);
            ctx.quadraticCurveTo(x, y + h, x, y + h - r);
            ctx.lineTo(x, y + r);
            ctx.quadraticCurveTo(x, y, x + r, y);
            ctx.closePath();
        }

        // Initial draw.
        drawImage();

        // Re-draw on customization input changes.
        $('#rtg-share-title, #rtg-share-subtitle, #rtg-share-footer').on('input', function() {
            drawImage();
        });

        // Regenerate button.
        $('#rtg-regenerate-image').on('click', function() {
            drawImage();
            showStatus('Image regenerated.');
        });

        // Download button.
        $('#rtg-download-image').on('click', function() {
            var link = document.createElement('a');
            link.download = 'rivian-tire-guide-stats.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            showStatus('Image downloaded.');
        });

        // Copy to clipboard button.
        $('#rtg-copy-image').on('click', function() {
            canvas.toBlob(function(blob) {
                if (!blob) {
                    showStatus('Failed to generate image.');
                    return;
                }
                if (navigator.clipboard && typeof ClipboardItem !== 'undefined') {
                    navigator.clipboard.write([
                        new ClipboardItem({ 'image/png': blob })
                    ]).then(function() {
                        showStatus('Image copied to clipboard!');
                    }).catch(function() {
                        showStatus('Copy failed. Try downloading instead.');
                    });
                } else {
                    showStatus('Clipboard API not supported in this browser. Try downloading instead.');
                }
            }, 'image/png');
        });

        function showStatus(msg) {
            var $el = $('#rtg-share-status');
            $el.text(msg).stop(true).css('opacity', 1);
            setTimeout(function() { $el.animate({ opacity: 0 }, 2000); }, 3000);
        }
    }

})(jQuery);
