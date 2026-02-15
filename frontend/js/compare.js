function rtgColor(name) {
  return getComputedStyle(document.documentElement).getPropertyValue('--rtg-' + name).trim();
}

function escapeHTML(str) {
  return String(str).replace(/[&<>"']/g, c =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c])
  );
}

function safeImageURL(url) {
  if (typeof url !== "string") return "";
  const trimmed = url.trim();
  const allowedHostnames = ["riviantrackr.com", "cdn.riviantrackr.com"];
  try {
    const u = new URL(trimmed);
    if (!/^https?:$/.test(u.protocol)) return "";
    if (!allowedHostnames.includes(u.hostname)) return "";
    if (u.pathname.includes('..') || u.pathname.includes('//')) return "";
    return trimmed;
  } catch {
    return "";
  }
}

function safeLinkURL(url) {
  if (typeof url !== "string" || !url.trim()) return "";
  const trimmed = url.trim();
  try {
    const urlObj = new URL(trimmed);
    if (urlObj.protocol !== 'https:') return "";
    if (urlObj.pathname.includes('..')) return "";
    const allowedDomains = [
      'riviantrackr.com', 'tirerack.com', 'discounttire.com', 'amazon.com', 'amzn.to',
      'costco.com', 'walmart.com', 'goodyear.com', 'bridgestonetire.com', 'michelinman.com',
      'continental-tires.com', 'pirelli.com', 'yokohamatire.com', 'coopertire.com',
      'bfgoodrichtires.com', 'firestone.com', 'generaltire.com', 'hankooktire.com',
      'kumhotire.com', 'toyo.com', 'falkentire.com', 'nittotire.com', 'simpletire.com',
      'prioritytire.com', 'evsportline.com', 'tsportline.com',
      'anrdoezrs.net', 'dpbolvw.net', 'jdoqocy.com', 'kqzyfj.com', 'tkqlhce.com',
      'commission-junction.com', 'cj.com', 'linksynergy.com', 'click.linksynergy.com',
      'shareasale.com', 'avantlink.com', 'impact.com', 'partnerize.com'
    ];
    const hostname = urlObj.hostname.toLowerCase();
    const isAllowed = allowedDomains.some(domain =>
      hostname === domain || hostname.endsWith('.' + domain)
    );
    if (!isAllowed) return "";
    return trimmed;
  } catch {
    return "";
  }
}

function getCompareIndexes() {
  const params = new URLSearchParams(window.location.search);
  return (params.get("compare") || "")
    .split(",")
    .map(n => parseInt(n.trim()))
    .filter(n => !isNaN(n));
}

function renderComparison(rows, indexes) {
  const selected = indexes.map(i => rows[i]).filter(Boolean);
  if (!selected.length) {
    document.getElementById("comparisonContent").innerHTML = "<p>No tires to compare.</p>";
    return;
  }

  const headers = [
    "Image", "Brand", "Model", "Size & Diameter", "Category", "Price", "Mileage Warranty",
    "Weight", "3PMS Rated", "Tread Depth", "Load Index", "Max Load", "Load Range", "Speed Rating",
    "Max PSI", "UTQG", "Tags", "Link", "Bundle Link"
  ];

  const colIndex = {
    "Image": 19, "Brand": 3, "Model": 4, "Efficiency": [20, 21], "Size & Diameter": [1, 2], "Category": 5,
    "Price": 6, "Mileage Warranty": 7, "Weight": 8, "3PMS Rated": 9, "Tread Depth": 10,
    "Load Index": 11, "Max Load": 12, "Load Range": 13, "Speed Rating": 14, "Max PSI": 15,
    "UTQG": 16, "Tags": 17, "Link": 18, "Bundle Link": 22
  };

  let html = `<table><thead><tr><th></th>${selected.map((_, i) => `<th>Tire ${i + 1}</th>`).join("")}</tr></thead><tbody>`;

  headers.forEach((label) => {
    html += `<tr><td><strong>${label}</strong></td>`;
    selected.forEach(r => {
      let cellValue;

      if (label === "Efficiency") {
        const score = escapeHTML(r[20] || "-");
        const grade = escapeHTML((r[21] || "-").toUpperCase());

        const colorMap = {
          A: rtgColor('accent') || "#5ec095",
          B: "#a3e635",
          C: "#facc15",
          D: "#f97316",
          E: "#ef4444",
          F: "#b91c1c"
        };

        const badgeColor = colorMap[grade] || "#94a3b8";

        cellValue = `
          <div style="
            display: inline-flex;
            align-items: center;
            font-size: 13px;
            font-weight: 400;
            border: 1px solid ${badgeColor};
            border-radius: 6px;
            overflow: hidden;
            line-height: 1;
          ">
            <span style="
              background-color: ${badgeColor};
              color: #1a1a1a;
              padding: 4px 10px;
              font-weight: 800;
              display: flex;
              align-items: center;
            ">${grade}</span>
            <span style="
              color: var(--rtg-text-light, #f1f5f9);
              background-color: var(--rtg-bg-primary, #1e293b);
              padding: 4px 10px;
              display: flex;
              align-items: center;
            ">${score}/100</span>
          </div>
        `;
      } else if (label === "Size & Diameter") {
        const [size, diameter] = colIndex["Size & Diameter"].map(i => r[i] || "-");
        const display = `${escapeHTML(size)} (${escapeHTML(diameter)})`;
        cellValue = `<span>${display}</span>`;
      } else {
        const i = colIndex[label];
        cellValue = r[i] || "-";

        if (label === "Tags" && cellValue !== "-") {
          const tags = cellValue.split(/[,|]/).map(tag => tag.trim()).filter(Boolean);
          cellValue = `
            <div style="
              display: flex;
              flex-wrap: wrap;
              gap: 6px;
              justify-content: flex-start;
              align-items: center;
            ">
              ${tags.map(tag => `
                <span style="
                  display: inline-block;
                  white-space: nowrap;
                  background: #e2e8f0;
                  color: #1a1a1a;
                  font-size: 12px;
                  padding: 4px 8px;
                  margin: 2px 0;
                  border-radius: 6px;
                  font-weight: 500;
                  line-height: 1.2;
                ">${escapeHTML(tag)}</span>
              `).join("")}
            </div>
          `;
        }

        if (label === "Image") {
          const validUrl = safeImageURL(cellValue);
          if (validUrl) {
            cellValue = `
              <div style="
                background: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                width: 100%;
                height: 160px;
                display: flex;
                justify-content: center;
                align-items: center;
              ">
                <img src="${escapeHTML(validUrl)}" alt="Tire Image" loading="lazy" style="
                  max-width: 100%;
                  max-height: 100%;
                  object-fit: contain;
                  background-color: #ffffff;
                  display: block;
                " />
              </div>
            `;
          } else {
            cellValue = "-";
          }
        }

        if (label === "Link") {
          const validUrl = safeLinkURL(cellValue);
          if (validUrl) {
            cellValue = `<a href="${escapeHTML(validUrl)}" target="_blank" rel="noopener noreferrer"
              style="
                display: inline-block;
                background-color: var(--rtg-accent, #5ec095);
                color: #1a1a1a;
                font-weight: 700;
                padding: 8px 16px;
                border-radius: 8px;
                text-align: center;
                text-decoration: none;
                transition: background-color 0.2s ease-in-out;
                margin: 2px 0;
              "
              onmouseover="this.style.backgroundColor='var(--rtg-accent-hover, #3ebd88)'"
              onmouseout="this.style.backgroundColor='var(--rtg-accent, #5ec095)'"
            >
              View Tire&nbsp;<i class="fa-solid fa-square-up-right"></i>
            </a>`;
          } else {
            cellValue = "-";
          }
        }

        if (label === "Bundle Link") {
          const validUrl = safeLinkURL(cellValue);
          if (validUrl) {
            cellValue = `<a href="${escapeHTML(validUrl)}" target="_blank" rel="noopener noreferrer"
                style="
                  display: inline-block;
                  background-color: #2563eb;
                  color: #FFFFFF;
                  font-weight: 700;
                  padding: 8px 16px;
                  border-radius: 8px;
                  text-align: center;
                  text-decoration: none;
                  transition: background-color 0.2s ease-in-out;
                  margin: 2px 0;
                "
                onmouseover="this.style.backgroundColor='#1d4ed8'"
                onmouseout="this.style.backgroundColor='#2563eb'"
              >
                Wheel & Tire from EV Sportline&nbsp;<i class="fa-solid fa-square-up-right"></i>
              </a>`;
          } else {
            cellValue = "-";
          }
        }

        if (label === "Price" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `$${escapeHTML(cellValue)}`;
        }
        if (label === "Weight" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `${escapeHTML(cellValue)} lb`;
        }
        if (label === "Mileage Warranty" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `${Number(cellValue).toLocaleString()} miles`;
        }
        if (label === "Max Load" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `${escapeHTML(cellValue)} lb`;
        }
      }

      html += `<td>${cellValue}</td>`;
    });
    html += `</tr>`;
  });

  html += "</tbody></table>";
  document.getElementById("comparisonContent").innerHTML = html;
}

// Load tire data from WordPress localized script (replaces PapaParse CSV fetch).
document.addEventListener("DOMContentLoaded", () => {
  const indexes = getCompareIndexes();
  if (!indexes.length) return;

  if (typeof rtgData !== 'undefined' && rtgData.tires && Array.isArray(rtgData.tires)) {
    renderComparison(rtgData.tires, indexes);

    const btn = document.getElementById("shareBtn");
    if (btn) {
      btn.addEventListener("click", () => {
        const url = window.location.href;
        navigator.clipboard.writeText(url)
          .then(() => {
            const icon = btn.querySelector("i");
            const originalClass = icon.className;
            icon.className = "fa-solid fa-check";
            setTimeout(() => icon.className = originalClass, 2000);
          })
          .catch(err => alert("Failed to copy link: " + err));
      });
    }
  } else {
    document.getElementById("comparisonContent").innerHTML = "<p>Tire data not available.</p>";
  }
});
