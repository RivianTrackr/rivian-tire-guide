<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="rtg-wrap">

    <div class="rtg-page-header">
        <div class="rtg-page-heading">
            <h1 class="rtg-page-title">Analytics</h1>
            <p class="rtg-page-subtitle">What shoppers click, search for and ask the advisor. Events are kept for the period set in <a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-settings#tab-analytics' ) ); ?>">Settings</a>.</p>
        </div>
        <div class="rtg-page-actions">
            <div class="rtg-segmented is-standalone" role="group" aria-label="Period">
                <button type="button" class="rtg-segmented-option rtg-filter-tab" data-period="7">7 days</button>
                <button type="button" class="rtg-segmented-option rtg-filter-tab is-active" data-period="30">30 days</button>
                <button type="button" class="rtg-segmented-option rtg-filter-tab" data-period="90">90 days</button>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="rtg-stats-grid" id="rtgAnalyticsSummary">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statTotalClicks">-</div>
            <div class="rtg-stat-label">Clicks</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statUniqueClickers">-</div>
            <div class="rtg-stat-label">Visitors who clicked</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statTotalSearches">-</div>
            <div class="rtg-stat-label">Searches</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statTotalAiQueries">-</div>
            <div class="rtg-stat-label">Advisor questions</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statUniqueSearchers">-</div>
            <div class="rtg-stat-label">Visitors who searched</div>
        </div>
    </div>

    <!-- Click Breakdown Cards -->
    <div class="rtg-stats-grid" id="rtgClickBreakdown">
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statPurchaseClicks">-</div>
            <div class="rtg-stat-label">Purchase clicks</div>
        </div>
        <div class="rtg-stat-card">
            <div class="rtg-stat-value" id="statReviewClicks">-</div>
            <div class="rtg-stat-label">Review clicks</div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="rtg-dashboard-grid">
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Clicks over time</h2></div>
            <div class="rtg-card-body">
                <canvas id="chartClicksDaily" height="260"></canvas>
                <p id="chartClicksEmpty" class="rtg-empty-line" style="display: none;">No click data for this period.</p>
            </div>
        </div>
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Search volume</h2></div>
            <div class="rtg-card-body">
                <canvas id="chartSearchesDaily" height="260"></canvas>
                <p id="chartSearchesEmpty" class="rtg-empty-line" style="display: none;">No search data for this period.</p>
            </div>
        </div>
    </div>

    <!-- Search vs AI Usage -->
    <div class="rtg-card">
            <div class="rtg-card-header"><h2>Search vs advisor</h2></div>
            <div class="rtg-card-body" id="searchVsAiContainer">
                <p class="rtg-empty-line">Loading...</p>
            </div>
        </div>

    <!-- Tables Row: Top Clicked + Top Searches -->
    <div class="rtg-dashboard-grid">
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Most clicked tires</h2></div>
            <div class="rtg-card-body" id="topClickedContainer">
                <p class="rtg-empty-line">Loading...</p>
            </div>
        </div>
        <div class="rtg-card">
            <div class="rtg-card-header"><h2>Top searches</h2></div>
            <div class="rtg-card-body" id="topSearchesContainer">
                <p class="rtg-empty-line">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Tables Row: Top AI Queries + Zero Results -->
    <div class="rtg-dashboard-grid">
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Top advisor questions</h2>
                <p>The questions shoppers ask "Help me choose" most often.</p>
            </div>
            <div class="rtg-card-body" id="topAiQueriesContainer">
                <p class="rtg-empty-line">Loading...</p>
            </div>
        </div>
        <div class="rtg-card">
            <div class="rtg-card-header">
                <h2>Searches with no results</h2>
                <p>Searches that returned no tires. Each one is a tire someone wanted and could not find.</p>
            </div>
            <div class="rtg-card-body" id="zeroResultsContainer">
                <p class="rtg-empty-line">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Filter Usage -->
    <div class="rtg-card">
        <div class="rtg-card-header"><h2>Most used filters</h2></div>
        <div class="rtg-card-body" id="filterUsageContainer">
            <p class="rtg-empty-line">Loading...</p>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    if (typeof rtgAnalytics === 'undefined') return;

    if (typeof Chart === 'undefined') {
        // The chart library comes from a CDN; when it is blocked the tables
        // still load but the two charts cannot draw.
        ['chartClicksEmpty', 'chartSearchesEmpty'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.textContent = 'The chart library could not be loaded, so this chart is unavailable. The figures below are unaffected.';
                el.style.display = 'block';
            }
        });
        ['chartClicksDaily', 'chartSearchesDaily'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }

    let currentPeriod = 30;
    let clicksChart = null;
    let searchesChart = null;

    // Period tab switching.
    document.querySelectorAll('.rtg-filter-tab[data-period]').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.rtg-filter-tab[data-period]').forEach(function(t) {
                t.classList.remove('is-active');
            });
            tab.classList.add('is-active');
            currentPeriod = parseInt(tab.dataset.period);
            loadAnalytics(currentPeriod);
        });
    });

    function loadAnalytics(period) {
        var formData = new FormData();
        formData.append('action', 'rtg_get_analytics');
        formData.append('nonce', rtgAnalytics.nonce);
        formData.append('period', period);

        fetch(rtgAnalytics.ajaxurl, {
            method: 'POST',
            body: formData,
        })
        .then(function(res) { return res.json(); })
        .then(function(json) {
            if (!json.success) {
                console.error('Analytics fetch failed:', json);
                return;
            }
            renderAnalytics(json.data);
        })
        .catch(function(err) {
            console.error('Analytics error:', err);
        });
    }

    function renderAnalytics(data) {
        // Summary cards.
        var s = data.summary || {};
        setText('statTotalClicks', numberFormat(s.total_clicks || 0));
        setText('statUniqueClickers', numberFormat(s.unique_clickers || 0));
        setText('statTotalSearches', numberFormat(s.total_searches || 0));
        setText('statTotalAiQueries', numberFormat(s.total_ai_queries || 0));
        setText('statUniqueSearchers', numberFormat(s.unique_searchers || 0));

        // Click breakdown by type.
        var clickTotals = {};
        (data.click_totals || []).forEach(function(row) {
            clickTotals[row.link_type] = parseInt(row.total) || 0;
        });
        setText('statPurchaseClicks', numberFormat(clickTotals.purchase || 0));
        setText('statReviewClicks', numberFormat(clickTotals.review || 0));

        // Clicks daily chart.
        renderClicksChart(data.clicks_daily || []);

        // Searches daily chart.
        renderSearchesChart(data.searches_daily || []);

        // Search vs AI usage breakdown.
        renderSearchVsAi(s.total_searches || 0, s.total_ai_queries || 0);

        // Top clicked tires.
        renderTopClicked(data.top_clicked || []);

        // Top search queries (regular only).
        renderTopSearches(data.top_searches || []);

        // Top AI queries.
        renderTopAiQueries(data.top_ai_queries || []);

        // Zero-result searches.
        renderZeroResults(data.zero_result_searches || []);

        // Filter usage.
        renderFilterUsage(data.filter_usage || []);
    }

    function renderClicksChart(dailyData) {
        var canvas = document.getElementById('chartClicksDaily');
        var emptyMsg = document.getElementById('chartClicksEmpty');

        if (typeof Chart === 'undefined') return;

        if (!dailyData.length) {
            canvas.style.display = 'none';
            emptyMsg.style.display = 'block';
            return;
        }
        canvas.style.display = 'block';
        emptyMsg.style.display = 'none';

        // Group by date and type.
        var dates = {};
        dailyData.forEach(function(row) {
            if (!dates[row.date]) dates[row.date] = { purchase: 0, review: 0 };
            dates[row.date][row.link_type] = parseInt(row.count) || 0;
        });

        var labels = Object.keys(dates).sort();
        var purchaseData = labels.map(function(d) { return dates[d].purchase; });
        var reviewData = labels.map(function(d) { return dates[d].review; });

        // Format labels as short dates.
        var shortLabels = labels.map(function(d) {
            var parts = d.split('-');
            return parseInt(parts[1]) + '/' + parseInt(parts[2]);
        });

        if (clicksChart) clicksChart.destroy();

        clicksChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: shortLabels,
                datasets: [
                    {
                        label: 'Purchase',
                        data: purchaseData,
                        borderColor: '#0071e3',
                        backgroundColor: 'rgba(0, 113, 227, 0.1)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: 'Review',
                        data: reviewData,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249, 115, 22, 0.1)',
                        fill: true,
                        tension: 0.3,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#6e6e73' } },
                },
                scales: {
                    x: { ticks: { color: '#86868b' }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: '#86868b', stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.06)' } },
                },
            },
        });
    }

    function renderSearchesChart(dailyData) {
        var canvas = document.getElementById('chartSearchesDaily');
        var emptyMsg = document.getElementById('chartSearchesEmpty');

        if (typeof Chart === 'undefined') return;

        if (!dailyData.length) {
            canvas.style.display = 'none';
            emptyMsg.style.display = 'block';
            return;
        }
        canvas.style.display = 'block';
        emptyMsg.style.display = 'none';

        var labels = dailyData.map(function(row) {
            var parts = row.date.split('-');
            return parseInt(parts[1]) + '/' + parseInt(parts[2]);
        });
        var counts = dailyData.map(function(row) { return parseInt(row.count) || 0; });

        if (searchesChart) searchesChart.destroy();

        searchesChart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Searches',
                    data: counts,
                    borderColor: '#a78bfa',
                    backgroundColor: 'rgba(167, 139, 250, 0.1)',
                    fill: true,
                    tension: 0.3,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: { ticks: { color: '#86868b' }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: '#86868b', stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.06)' } },
                },
            },
        });
    }

    function renderSearchVsAi(totalSearches, totalAi) {
        var container = document.getElementById('searchVsAiContainer');
        var total = totalSearches + totalAi;

        if (total === 0) {
            container.innerHTML = '<p class="rtg-empty-line">No search or advisor data for this period.</p>';
            return;
        }

        var searchPct = Math.round((totalSearches / total) * 100);
        var aiPct = 100 - searchPct;

        var html = '<div class="rtg-split-row">';

        // Bar visualization
        html += '<div class="rtg-split-bar-wrap"><div class="rtg-split-bar">';
        if (searchPct > 0) {
            html += '<div class="rtg-split-bar-segment is-a" style="width: ' + searchPct + '%;">' + searchPct + '%</div>';
        }
        if (aiPct > 0) {
            html += '<div class="rtg-split-bar-segment is-b" style="width: ' + aiPct + '%;">' + aiPct + '%</div>';
        }
        html += '</div></div>';

        // Legend
        html += '<div class="rtg-legend">' +
            '<span class="rtg-legend-item"><span class="rtg-legend-swatch is-a"></span>Search <strong>' + numberFormat(totalSearches) + '</strong></span>' +
            '<span class="rtg-legend-item"><span class="rtg-legend-swatch is-b"></span>Advisor <strong>' + numberFormat(totalAi) + '</strong></span>' +
        '</div>';

        html += '</div>';
        container.innerHTML = html;
    }

    function renderTopClicked(items) {
        var container = document.getElementById('topClickedContainer');
        if (!items.length) {
            container.innerHTML = '<p class="rtg-empty-line">No click data yet.</p>';
            return;
        }

        var html = '<ul class="rtg-mini-list">';
        items.forEach(function(tire, i) {
            var img = tire.image ? '<img src="' + escHtml(tire.image) + '" alt="" class="rtg-mini-list-thumb">' : '';
            html += '<li class="rtg-mini-list-item">' +
                '<span class="rtg-mini-list-rank">' + (i + 1) + '</span>' +
                img +
                '<span class="rtg-mini-list-info">' +
                    '<span class="rtg-mini-list-name">' + escHtml(tire.brand) + ' ' + escHtml(tire.model) + '</span>' +
                    '<span class="rtg-mini-list-meta">' + numberFormat(tire.unique_clicks) + ' unique visitor' + (parseInt(tire.unique_clicks) !== 1 ? 's' : '') + '</span>' +
                '</span>' +
                '<span class="rtg-mini-list-value">' + numberFormat(tire.click_count) + ' click' + (parseInt(tire.click_count) !== 1 ? 's' : '') + '</span>' +
            '</li>';
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    function renderTopSearches(items) {
        var container = document.getElementById('topSearchesContainer');
        if (!items.length) {
            container.innerHTML = '<p class="rtg-empty-line">No search data yet.</p>';
            return;
        }

        var html = '<ul class="rtg-mini-list">';
        items.forEach(function(row, i) {
            html += '<li class="rtg-mini-list-item">' +
                '<span class="rtg-mini-list-rank">' + (i + 1) + '</span>' +
                '<span class="rtg-mini-list-info">' +
                    '<span class="rtg-mini-list-name">&ldquo;' + escHtml(row.search_query) + '&rdquo;</span>' +
                    '<span class="rtg-mini-list-meta">Avg ' + numberFormat(row.avg_results) + ' result' + (parseInt(row.avg_results) !== 1 ? 's' : '') + '</span>' +
                '</span>' +
                '<span class="rtg-mini-list-value">' + numberFormat(row.count) + '&times;</span>' +
            '</li>';
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    function renderTopAiQueries(items) {
        var container = document.getElementById('topAiQueriesContainer');
        if (!items.length) {
            container.innerHTML = '<p class="rtg-empty-line">No advisor questions yet.</p>';
            return;
        }

        var html = '<ul class="rtg-mini-list">';
        items.forEach(function(row, i) {
            html += '<li class="rtg-mini-list-item">' +
                '<span class="rtg-mini-list-rank">' + (i + 1) + '</span>' +
                '<span class="rtg-mini-list-info">' +
                    '<span class="rtg-mini-list-name">&ldquo;' + escHtml(row.search_query) + '&rdquo;</span>' +
                    '<span class="rtg-mini-list-meta">Avg ' + numberFormat(row.avg_results) + ' result' + (parseInt(row.avg_results) !== 1 ? 's' : '') + '</span>' +
                '</span>' +
                '<span class="rtg-mini-list-value">' + numberFormat(row.count) + '&times;</span>' +
            '</li>';
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    function renderZeroResults(items) {
        var container = document.getElementById('zeroResultsContainer');
        if (!items.length) {
            container.innerHTML = '<p class="rtg-empty-line">Every search found at least one tire.</p>';
            return;
        }

        var html = '<ul class="rtg-mini-list">';
        items.forEach(function(row, i) {
            html += '<li class="rtg-mini-list-item">' +
                '<span class="rtg-mini-list-rank">' + (i + 1) + '</span>' +
                '<span class="rtg-mini-list-info">' +
                    '<span class="rtg-mini-list-name">&ldquo;' + escHtml(row.search_query) + '&rdquo;</span>' +
                    '<span class="rtg-mini-list-meta">0 results</span>' +
                '</span>' +
                '<span class="rtg-mini-list-value">' + numberFormat(row.count) + '&times;</span>' +
            '</li>';
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    function renderFilterUsage(items) {
        var container = document.getElementById('filterUsageContainer');
        if (!items.length) {
            container.innerHTML = '<p class="rtg-empty-line">No filter usage data yet.</p>';
            return;
        }

        // Aggregate by individual filter keys across all JSON entries.
        var filterCounts = {};
        items.forEach(function(row) {
            try {
                var filters = JSON.parse(row.filters_json);
                var count = parseInt(row.count) || 0;
                Object.keys(filters).forEach(function(key) {
                    var label = key;
                    var val = filters[key];
                    if (val !== true && val !== false) {
                        label = key + ': ' + val;
                    }
                    if (!filterCounts[label]) filterCounts[label] = 0;
                    filterCounts[label] += count;
                });
            } catch (e) {}
        });

        // Sort by count desc.
        var sorted = Object.entries(filterCounts).sort(function(a, b) { return b[1] - a[1]; }).slice(0, 15);

        if (!sorted.length) {
            container.innerHTML = '<p class="rtg-empty-line">No filter usage data yet.</p>';
            return;
        }

        var maxCount = sorted[0][1];
        var html = '<ul class="rtg-bar-list">';
        sorted.forEach(function(pair) {
            var pct = Math.round((pair[1] / maxCount) * 100);
            html += '<li class="rtg-bar-item">' +
                '<span class="rtg-bar-label">' + escHtml(pair[0]) + '</span>' +
                '<span class="rtg-bar-track"><span class="rtg-bar-fill" style="width: ' + pct + '%;"></span></span>' +
                '<span class="rtg-bar-count">' + numberFormat(pair[1]) + '</span>' +
            '</li>';
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    // Helpers.
    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function numberFormat(n) {
        return parseInt(n).toLocaleString();
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // Initial load.
    loadAnalytics(currentPeriod);
});
</script>
