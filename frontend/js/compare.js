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
    const encoded = encodeURIComponent(trimmed);
    return `https://cdn.riviantrackr.com/spio/w_600+q_auto+ret_img+to_webp/${encoded}`;
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
        const score = r[20] || "-";
        const grade = (r[21] || "-").toUpperCase();

        const colorMap = {
          A: "#5ec095",
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
              color: #f1f5f9;
              background-color: #1e293b;
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

        if (label === "Image" && cellValue !== "-" && cellValue.startsWith("http")) {
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
              <img src="${cellValue}" alt="Tire Image" style="
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                background-color: #ffffff;
                display: block;
              " />
            </div>
          `;
        }

        if (label === "Link" && cellValue !== "-" && cellValue.startsWith("http")) {
          cellValue = `<a href="${cellValue}" target="_blank" rel="noopener noreferrer"
            style="
              display: inline-block;
              background-color: #5ec095;
              color: #1a1a1a;
              font-weight: 700;
              padding: 8px 16px;
              border-radius: 8px;
              text-align: center;
              text-decoration: none;
              transition: background-color 0.2s ease-in-out;
              margin: 2px 0;
            "
            onmouseover="this.style.backgroundColor='#3ebd88'"
            onmouseout="this.style.backgroundColor='#5ec095'"
          >
            View Tire&nbsp;<i class="fa-solid fa-square-up-right"></i>
          </a>`;
        }

        if (label === "Bundle Link" && cellValue !== "-" && cellValue.startsWith("http")) {
          cellValue = `<a href="${cellValue}" target="_blank" rel="noopener noreferrer"
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
              onmouseover="this.style.backgroundColor='#2563eb'"
              onmouseout="this.style.backgroundColor='#2563eb'"
            >
              Wheel & Tire from EV Sportline&nbsp;<i class="fa-solid fa-square-up-right"></i>
            </a>`;
        }

        if (label === "Price" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `$${cellValue}`;
        }
        if (label === "Weight" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `${cellValue} lb`;
        }
        if (label === "Mileage Warranty" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `${Number(cellValue).toLocaleString()} miles`;
        }
        if (label === "Max Load" && cellValue !== "-" && !isNaN(cellValue)) {
          cellValue = `${cellValue} lb`;
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
