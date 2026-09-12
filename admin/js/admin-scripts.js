/* Admin scripts for Rivian Tire Guide */
(function($) {
    'use strict';

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

        // Confirm bulk delete. Bound to the list form only — a document-wide
        // handler used to read the bulk-action select from anywhere, so
        // submitting the search form with "Delete" selected popped the
        // delete confirmation.
        $('select[name="rtg_bulk_action"]').closest('form').on('submit', function() {
            var action = $(this).find('select[name="rtg_bulk_action"]').val();
            if (action === 'delete') {
                var $checked = $(this).find('input[name="tire_ids[]"]:checked');
                if ($checked.length === 0) {
                    alert('No tires selected.');
                    return false;
                }
                var reviews = 0;
                $checked.each(function () {
                    reviews += parseInt($(this).data('review-count'), 10) || 0;
                });
                var message = 'Delete ' + $checked.length + ' tire(s)';
                if (reviews > 0) {
                    message += ' and ' + reviews + ' review' + (reviews === 1 ? '' : 's');
                }
                return confirm(message + '? This cannot be undone.');
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
            } else {
                tags.push(tag);
            }
            $tagsInput.val(tags.join(', ')).trigger('input');
            syncTagChips();
        });

        // Chips reflect the field, so typing a tag by hand highlights it too.
        function syncTagChips() {
            var $tagsInput = $('#tags');
            if (!$tagsInput.length) {
                return;
            }
            var currentTags = $tagsInput.val().split(',').map(function(t) { return t.trim(); });
            $('.rtg-tag-suggestion').each(function() {
                var on = currentTags.indexOf(String($(this).data('tag'))) > -1;
                $(this).toggleClass('is-selected', on).attr('aria-pressed', on ? 'true' : 'false');
            });
        }
        syncTagChips();
        $('#tags').on('input', syncTagChips);

        // --- Shared page behaviors ---
        initTabs();
        initCollapsibles();
        initColorFields();
        initUnsavedIndicator();

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

        // (The real-time efficiency calculator was removed in 1.58.0 along
        // with the edit form's efficiency card — the score still auto-
        // calculates server-side on save; the metric is no longer surfaced
        // in admin since it was retired from the frontend in 1.51.0.)
    });


    // ==========================================================================
    // Tabs — sections of one page.
    //
    // <div data-rtg-tabs data-default="general">
    //   <nav class="rtg-tabs"><button class="rtg-tab" data-tab="general">…</button></nav>
    //   <div class="rtg-tab-panel" data-tab-panel="general">…</div>
    // </div>
    //
    // The open tab rides in the URL hash (#tab-general) so a reload, a back
    // button and a link from another page all land on the same section.
    // Panels are hidden, never removed, so a form spanning tabs still submits
    // every field.
    // ==========================================================================

    function initTabs() {
        $('[data-rtg-tabs]').each(function() {
            var $root = $(this);
            var $tabs = $root.find('.rtg-tab[data-tab]');
            var $panels = $root.find('[data-tab-panel]');
            if (!$tabs.length) {
                return;
            }

            function names() {
                return $tabs.map(function() { return $(this).data('tab'); }).get();
            }

            function activate(name, pushHash) {
                if (names().indexOf(name) === -1) {
                    name = $root.data('default') || $tabs.first().data('tab');
                }
                $tabs.each(function() {
                    var on = $(this).data('tab') === name;
                    $(this).toggleClass('is-active', on)
                        .attr('aria-selected', on ? 'true' : 'false')
                        .attr('tabindex', on ? '0' : '-1');
                });
                $panels.each(function() {
                    var on = $(this).data('tab-panel') === name;
                    this.hidden = !on;
                });
                if (pushHash && window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#tab-' + name);
                }
                $root.trigger('rtg:tab', [name]);
            }

            $tabs.attr('role', 'tab');
            $root.find('.rtg-tabs').attr('role', 'tablist');
            $panels.attr('role', 'tabpanel');

            $tabs.on('click', function(e) {
                e.preventDefault();
                activate($(this).data('tab'), true);
            });

            // Left / right arrows move between tabs, as a tablist should.
            $tabs.on('keydown', function(e) {
                if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                    return;
                }
                e.preventDefault();
                var list = names();
                var i = list.indexOf($(this).data('tab'));
                var next = e.key === 'ArrowRight' ? (i + 1) % list.length : (i - 1 + list.length) % list.length;
                activate(list[next], true);
                $tabs.eq(next).trigger('focus');
            });

            var fromHash = (window.location.hash || '').replace(/^#tab-/, '');
            var initial = fromHash && names().indexOf(fromHash) > -1 ? fromHash : ($root.data('default') || $tabs.first().data('tab'));
            activate(initial, false);

            $(window).on('hashchange', function() {
                var h = (window.location.hash || '').replace(/^#tab-/, '');
                if (h && names().indexOf(h) > -1) {
                    activate(h, false);
                }
            });
        });
    }

    // ==========================================================================
    // Collapsible cards — a header that opens and closes the card body.
    //
    // <div class="rtg-card-header rtg-card-toggle" data-rtg-collapse="#panel-id" aria-expanded="false">
    // ==========================================================================

    function initCollapsibles() {
        $('[data-rtg-collapse]').each(function() {
            var $toggle = $(this);
            var $panel = $($toggle.data('rtg-collapse'));
            if (!$panel.length) {
                return;
            }
            var open = $toggle.attr('aria-expanded') === 'true';
            $panel.toggle(open);
            $toggle.attr({ role: 'button', tabindex: '0', 'aria-expanded': open ? 'true' : 'false' });

            function flip() {
                var isOpen = $toggle.attr('aria-expanded') === 'true';
                $toggle.attr('aria-expanded', isOpen ? 'false' : 'true');
                $panel.stop(true, true).slideToggle(200);
            }

            $toggle.on('click', function(e) {
                if ($(e.target).closest('a, button, input, select, label').length) {
                    return;
                }
                flip();
            });
            $toggle.on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    flip();
                }
            });
        });
    }

    // ==========================================================================
    // Color fields — a native picker beside the hex input, kept in step.
    // Only the text input is named, so what saves is unchanged.
    // ==========================================================================

    function initColorFields() {
        $('.rtg-color-field').each(function() {
            var $text = $(this).find('input[type="text"]');
            var $picker = $(this).find('input[type="color"]');
            if (!$text.length || !$picker.length) {
                return;
            }
            $text.on('input', function() {
                var val = $text.val().trim();
                if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                    $picker.val(val.toLowerCase());
                }
            });
            $picker.on('input', function() {
                $text.val($picker.val()).trigger('change');
            });
        });
    }

    // ==========================================================================
    // Sticky save bar — says when the form above it has unsaved edits.
    // ==========================================================================

    function initUnsavedIndicator() {
        $('.rtg-footer-actions.is-sticky').each(function() {
            var $bar = $(this);
            var $form = $bar.closest('form');
            if (!$form.length) {
                return;
            }
            var dirty = false;
            $form.on('input change', 'input, select, textarea', function() {
                if (!dirty) {
                    dirty = true;
                    $bar.addClass('has-changes');
                }
            });
            $form.on('submit', function() {
                dirty = false;
                $bar.removeClass('has-changes');
            });
        });
    }

    // Copy any field's value: <button data-rtg-copy="#field-id" data-copied="Copied!">
    $(document).on('click', '[data-rtg-copy]', function() {
        var $btn = $(this);
        var $input = $($btn.data('rtg-copy'));
        var $status = $($btn.data('rtg-copy-status'));
        if (!$input.length) {
            return;
        }
        var value = $input.val();

        function done() {
            if ($status.length) {
                $status.stop(true).show().css('opacity', 1);
                setTimeout(function() { $status.fadeOut(400); }, 2000);
            }
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done);
        } else {
            $input[0].select();
            document.execCommand('copy');
            done();
        }
    });

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
            bgPrimary:   '#16191e',
            bgCard:      '#16191e',
            bgDeep:      '#121418',
            accent:      '#fba919',
            accentHover: '#fba919',
            textPrimary: '#ece9e4',
            textLight:   '#ece9e4',
            textMuted:   '#ece9e4',
            textHeading: '#ece9e4',
            border:      '#3a3e45',
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
                { label: 'Avg Efficiency',     value: data.avgEfficiency + ' / 100' },
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
                var tireLabel = data.topTire + (data.topSize ? ' (' + data.topSize + ')' : '');
                ctx.fillText(tireLabel, 195, calloutY + 30);

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
