

(function () {
  function getReservations() {
    return typeof window.getStoredReservations === "function" ? window.getStoredReservations() : [];
  }

  function toIsoDate(raw) {
    if (!raw) return "";
    const d = new Date(raw);
    return Number.isNaN(d.getTime()) ? String(raw).slice(0, 10) : d.toISOString().slice(0, 10);
  }

  function recordDateIso(r) {
    return toIsoDate(r.checkIn || r.checkInDate || r.preferredDate || r.eventDate || "");
  }

  function filterRecords(records, { from = "", to = "", status = "all", room = "all" } = {}) {
    return records.filter((r) => {
      const iso = recordDateIso(r);
      if (from && iso && iso < from) return false;
      if (to && iso && iso > to) return false;
      if (status !== "all" && (r.paymentStatus || "") !== status) return false;
      if (room !== "all" && (r.roomName || r.eventType || "") !== room) return false;
      return true;
    });
  }

  function fmtDate(raw) {
    if (!raw) return "\u2014";
    const d = new Date(raw);
    return Number.isNaN(d.getTime())
      ? String(raw)
      : d.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
  }

  const REPORT_COLUMNS = {
    reservation: [
      ["id", "Reservation ID"],
      ["customerName", "Guest"],
      ["roomOrEvent", "Room / Event"],
      ["checkIn", "Check-in"],
      ["checkOut", "Check-out"],
      ["guests", "Guests"],
      ["totalAmount", "Amount"],
      ["paymentStatus", "Payment Status"],
      ["quoteStatus", "Quotation"],
    ],
    booking: [
      ["id", "Booking ID"],
      ["customerName", "Guest"],
      ["roomOrEvent", "Room / Event"],
      ["checkIn", "Check-in"],
      ["checkOut", "Check-out"],
      ["guests", "Guests"],
      ["totalAmount", "Amount"],
      ["paymentStatus", "Status"],
    ],
    payment: [
      ["id", "Reservation ID"],
      ["customerName", "Customer Name"],
      ["dateReserved", "Date Reserved"],
      ["totalAmount", "Amount"],
      ["proofRef", "Proof of Payment"],
      ["paymentStatus", "Payment Status"],
    ],
    schedule: [
      ["id", "Reservation ID"],
      ["customerName", "Guest"],
      ["roomOrEvent", "Room / Event"],
      ["checkIn", "Check-in / Event date"],
      ["checkOut", "Check-out"],
      ["guests", "Guests"],
    ],
  };

  function toRow(r) {
    return {
      id: r.id || "",
      customerName: r.customerName || "",
      roomOrEvent: r.roomName || r.eventType || "",
      checkIn: fmtDate(r.checkIn || r.preferredDate),
      checkOut: fmtDate(r.checkOut || r.preferredDate),
      guests: String(r.guests || r.guestsLabel || r.eventGuests || ""),
      totalAmount: r.totalAmount || "",
      paymentStatus: r.paymentStatus || "",
      quoteStatus: r.quotation?.status || "\u2014",
      dateReserved: fmtDate(r.createdAt || r.reservedAt || r.checkIn || r.preferredDate),
      proofRef: r.proofOfPayment?.filename || r.proofOfPayment?.storageRef || "\u2014",
    };
  }

  function buildDataset(records, reportType) {
    const columns = REPORT_COLUMNS[reportType] || REPORT_COLUMNS.reservation;
    return { columns, rows: records.map(toRow) };
  }

  function toCsv({ columns, rows }) {
    const esc = (v) => {
      const s = String(v ?? "");
      return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const lines = [columns.map(([, label]) => esc(label)).join(",")];
    rows.forEach((row) => lines.push(columns.map(([key]) => esc(row[key])).join(",")));
    return lines.join("\r\n");
  }

  function downloadBlob(content, filename, mime) {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
  }

  function safeStamp() {
    return new Date().toISOString().slice(0, 10);
  }

  function exportCsv(dataset, baseName) {

    downloadBlob("\ufeff" + toCsv(dataset), `${baseName}-${safeStamp()}.csv`, "text/csv;charset=utf-8;");
  }

  const CRC_TABLE = (() => {
    const t = new Uint32Array(256);
    for (let i = 0; i < 256; i++) {
      let c = i;
      for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
      t[i] = c >>> 0;
    }
    return t;
  })();

  function crc32(bytes) {
    let c = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  }

  function zipStore(files) {
    const enc = new TextEncoder();
    const chunks = [];
    const central = [];
    let offset = 0;

    const num = (value, size) => {
      const out = new Uint8Array(size);
      let v = value;
      for (let i = 0; i < size; i++) {
        out[i] = v & 0xff;
        v = Math.floor(v / 256);
      }
      return out;
    };
    const push = (arr, target) => {
      target.push(arr);
      return arr.length;
    };

    files.forEach(({ name, content }) => {
      const nameBytes = enc.encode(name);
      const data = enc.encode(content);
      const crc = crc32(data);
      const local = [];
      push(new Uint8Array([0x50, 0x4b, 0x03, 0x04]), local);
      push(num(20, 2), local);
      push(num(0, 2), local);
      push(num(0, 2), local);
      push(num(0, 2), local);
      push(num(0, 2), local);
      push(num(crc, 4), local);
      push(num(data.length, 4), local);
      push(num(data.length, 4), local);
      push(num(nameBytes.length, 2), local);
      push(num(0, 2), local);
      push(nameBytes, local);
      push(data, local);
      const localSize = local.reduce((sum, a) => sum + a.length, 0);

      const cd = [];
      push(new Uint8Array([0x50, 0x4b, 0x01, 0x02]), cd);
      push(num(20, 2), cd);
      push(num(20, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 2), cd);
      push(num(crc, 4), cd);
      push(num(data.length, 4), cd);
      push(num(data.length, 4), cd);
      push(num(nameBytes.length, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 2), cd);
      push(num(0, 4), cd);
      push(num(offset, 4), cd);
      push(nameBytes, cd);

      chunks.push(...local);
      central.push(...cd);
      offset += localSize;
    });

    const centralSize = central.reduce((sum, a) => sum + a.length, 0);
    const end = [
      new Uint8Array([0x50, 0x4b, 0x05, 0x06]),
      num(0, 2),
      num(0, 2),
      num(files.length, 2),
      num(files.length, 2),
      num(centralSize, 4),
      num(offset, 4),
      num(0, 2),
    ];

    return new Blob([...chunks, ...central, ...end], {
      type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    });
  }

  function xmlEscape(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function docxParagraph(text, { bold = false, size = 22, color = "333333", italic = false } = {}) {
    return `<w:p><w:pPr><w:spacing w:after="120"/></w:pPr><w:r><w:rPr>${bold ? "<w:b/>" : ""}${
      italic ? "<w:i/>" : ""
    }<w:color w:val="${color}"/><w:sz w:val="${size}"/></w:rPr><w:t xml:space="preserve">${xmlEscape(text)}</w:t></w:r></w:p>`;
  }

  function docxCell(text, width, { header = false } = {}) {
    return `<w:tc><w:tcPr><w:tcW w:w="${width}" w:type="dxa"/>${
      header ? '<w:shd w:val="clear" w:color="auto" w:fill="0C3B5D"/>' : ""
    }</w:tcPr><w:p><w:pPr><w:spacing w:after="0"/></w:pPr><w:r><w:rPr>${header ? "<w:b/>" : ""}<w:color w:val="${
      header ? "FFFFFF" : "333333"
    }"/><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">${xmlEscape(text)}</w:t></w:r></w:p></w:tc>`;
  }

  function exportDocx(dataset, title, baseName) {
    const totalWidth = 14400;
    const colWidth = Math.floor(totalWidth / Math.max(dataset.columns.length, 1));
    const headerRow = `<w:tr>${dataset.columns
      .map(([, label]) => docxCell(label, colWidth, { header: true }))
      .join("")}</w:tr>`;
    const bodyRows = dataset.rows
      .map(
        (row) =>
          `<w:tr>${dataset.columns.map(([key]) => docxCell(row[key] ?? "", colWidth)).join("")}</w:tr>`,
      )
      .join("");
    const emptyRow = dataset.rows.length
      ? ""
      : `<w:tr>${docxCell("No records match the selected filters.", totalWidth)}</w:tr>`;

    const documentXml = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
${docxParagraph("Casa Vergara Resort", { bold: true, size: 32, color: "0C3B5D" })}
${docxParagraph(title, { bold: true, size: 24, color: "0C3B5D" })}
${docxParagraph(`Generated ${new Date().toLocaleString()} \u2014 ${dataset.rows.length} record(s)`, {
  size: 18,
  color: "5A5A5A",
})}
<w:tbl><w:tblPr><w:tblW w:w="${totalWidth}" w:type="dxa"/><w:tblBorders>
<w:top w:val="single" w:sz="4" w:color="C9D8E6"/><w:left w:val="single" w:sz="4" w:color="C9D8E6"/>
<w:bottom w:val="single" w:sz="4" w:color="C9D8E6"/><w:right w:val="single" w:sz="4" w:color="C9D8E6"/>
<w:insideH w:val="single" w:sz="4" w:color="C9D8E6"/><w:insideV w:val="single" w:sz="4" w:color="C9D8E6"/>
</w:tblBorders></w:tblPr>${headerRow}${bodyRows}${emptyRow}</w:tbl>
${docxParagraph("Generated automatically by the Casa Vergara admin portal.", { size: 16, color: "8A8A8A", italic: true })}
<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr>
</w:body></w:document>`;

    const blob = zipStore([
      {
        name: "[Content_Types].xml",
        content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>`,
      },
      {
        name: "_rels/.rels",
        content: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>`,
      },
      { name: "word/document.xml", content: documentXml },
    ]);

    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${baseName}-${safeStamp()}.docx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
    return true;
  }

  function exportPdf(dataset, title, baseName) {
    const jsPdfLib = window.jspdf?.jsPDF;
    if (!jsPdfLib) {
      window.alert("PDF export isn't available on this page right now. Please try again in a moment.");
      return false;
    }
    const doc = new jsPdfLib({
      unit: "pt",
      format: "a4",
      orientation: dataset.columns.length > 6 ? "landscape" : "portrait",
    });
    const marginLeft = 40;
    const pageWidth = doc.internal.pageSize.getWidth();
    const bottomLimit = doc.internal.pageSize.getHeight() - 50;
    const usableWidth = pageWidth - marginLeft * 2;
    const colWidth = usableWidth / dataset.columns.length;
    const rowHeight = 20;
    let y = 50;

    doc.setFillColor(12, 59, 93);
    doc.rect(0, 0, pageWidth, 64, "F");
    doc.setTextColor(255, 255, 255);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(16);
    doc.text("Casa Vergara Resort", marginLeft, 30);
    doc.setFontSize(11);
    doc.setFont("helvetica", "normal");
    doc.text(title, marginLeft, 48);

    y = 92;
    doc.setTextColor(90, 90, 90);
    doc.setFontSize(9);
    doc.text(`Generated ${new Date().toLocaleString()}`, marginLeft, y);
    y += 20;

    function drawHeader() {
      doc.setFont("helvetica", "bold");
      doc.setFontSize(9);
      doc.setTextColor(12, 59, 93);
      doc.setDrawColor(220, 230, 240);
      doc.line(marginLeft, y, pageWidth - marginLeft, y);
      y += 14;
      dataset.columns.forEach(([, label], i) => doc.text(String(label), marginLeft + i * colWidth, y));
      y += 8;
      doc.line(marginLeft, y, pageWidth - marginLeft, y);
      y += 14;
      doc.setFont("helvetica", "normal");
      doc.setTextColor(50, 50, 50);
    }

    drawHeader();
    dataset.rows.forEach((row) => {
      if (y > bottomLimit) {
        doc.addPage();
        y = 50;
        drawHeader();
      }
      dataset.columns.forEach(([key], i) => {
        const text = String(row[key] ?? "");
        doc.text(text.length > 30 ? text.slice(0, 29) + "\u2026" : text, marginLeft + i * colWidth, y);
      });
      y += rowHeight;
    });

    if (!dataset.rows.length) {
      doc.setFont("helvetica", "italic");
      doc.text("No records match the selected filters.", marginLeft, y);
    }

    doc.save(`${baseName}-${safeStamp()}.pdf`);
    return true;
  }

  function setStatus(el, text, tone = "muted") {
    if (!el) return;
    el.textContent = text;
    el.classList.remove("d-none", "text-success", "text-danger", "text-muted-brand");
    el.classList.add(tone === "error" ? "text-danger" : tone === "ok" ? "text-success" : "text-muted-brand");
  }

  function withBusy(btn, fn) {
    if (!btn) return fn();
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Working\u2026`;
    try {
      return fn();
    } finally {
      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = original;
      }, 250);
    }
  }

  function downloadReservationPageExport(format, btn, statusEl) {
    withBusy(btn, () => {
      const tableBody = document.getElementById("reservationTableBody");
      if (!tableBody) return;
      const rows = Array.from(tableBody.querySelectorAll("tr[data-id]")).filter(
        (tr) => tr.id !== "reservationEmptyStateRow" && !tr.classList.contains("d-none"),
      );
      const columns = [
        ["id", "ID"],
        ["guest", "Guest"],
        ["room", "Room / Event"],
        ["checkIn", "Check-in"],
        ["checkOut", "Check-out"],
        ["guests", "Guests"],
        ["amount", "Amount"],
        ["payStatus", "Payment Status"],
        ["quoteStatus", "Quotation"],
      ];

      const dataRows = rows.map((tr) => {
        const cells = tr.querySelectorAll("td");
        const ds = tr.dataset;
        return {
          id: ds.id || cells[0]?.textContent.trim() || "",
          guest: ds.exportGuest || cells[1]?.textContent.trim() || "",
          room: ds.room || cells[2]?.textContent.trim() || "",
          checkIn: ds.exportCheckin || cells[3]?.textContent.trim() || "",
          checkOut: ds.exportCheckout || cells[4]?.textContent.trim() || "",
          guests: ds.exportGuests || cells[5]?.textContent.trim() || "",
          amount: ds.exportAmount || cells[4]?.textContent.trim() || "",
          payStatus: ds.status || cells[5]?.textContent.trim() || "",
          quoteStatus: ds.quoteStatus || cells[6]?.textContent.trim() || "",
        };
      });
      const dataset = { columns, rows: dataRows };
      if (format === "pdf") {
        if (exportPdf(dataset, "Reservations Export", "CasaVergara-Reservations")) {
          setStatus(statusEl, `Exported ${dataRows.length} reservation(s) as PDF.`, "ok");
        }
      } else {
        exportDocx(dataset, "Reservations Export", "CasaVergara-Reservations");
        setStatus(statusEl, `Exported ${dataRows.length} reservation(s) as Word (.docx).`, "ok");
      }
    });
  }

  const PAYMENT_PROOF_COLUMNS = [
    ["reservationId", "Reservation ID"],
    ["customerName", "Guest"],
    ["email", "Email"],
    ["submitted", "Date Submitted"],
    ["amount", "Amount"],
    ["paymentMethod", "Method"],
    ["status", "Payment Status"],
  ];

  function proofSubmittedIso(proof) {
    if (!proof.createdAt) return "";
    const d = new Date(String(proof.createdAt).replace(" ", "T"));
    return Number.isNaN(d.getTime()) ? "" : d.toISOString().slice(0, 10);
  }

  function filterProofs(proofs, { from = "", to = "", status = "all" } = {}) {
    return proofs.filter((p) => {
      const iso = proofSubmittedIso(p);
      if (from && iso && iso < from) return false;
      if (to && iso && iso > to) return false;
      if (status !== "all" && (p.status || "") !== status) return false;
      return true;
    });
  }

  function buildProofDataset(proofs) {
    const rows = proofs.map((p) => ({
      reservationId: p.reservationId || "",
      customerName: p.customerName || "Guest",
      email: p.email || "",
      submitted: p.createdAt ? new Date(String(p.createdAt).replace(" ", "T")).toLocaleString() : "\u2014",
      amount: p.amount || "\u2014",
      paymentMethod: p.paymentMethod || "\u2014",
      status: p.status || "",
    }));
    return { columns: PAYMENT_PROOF_COLUMNS, rows };
  }

  function initAdminPaymentsExport() {
    const csvBtn =
      document.getElementById("paymentExportDocxBtn") || document.getElementById("paymentExportCsvBtn");
    const pdfBtn = document.getElementById("paymentExportPdfBtn");
    const statusEl = document.getElementById("paymentExportStatus");
    const fromEl = document.getElementById("paymentExportDateFrom");
    const toEl = document.getElementById("paymentExportDateTo");
    const statusFilterEl = document.getElementById("paymentExportPayFilter");
    if (!csvBtn && !pdfBtn) return;

    function run(format, btn) {
      withBusy(btn, () => {
        const allProofs = typeof window.getAdminPaymentProofs === "function" ? window.getAdminPaymentProofs() : [];
        const proofs = filterProofs(allProofs, {
          from: fromEl?.value || "",
          to: toEl?.value || "",
          status: statusFilterEl?.value || "all",
        });
        const dataset = buildProofDataset(proofs);

        if (format === "pdf") {
          if (!exportPdf(dataset, "Payment Records", "CasaVergara-Payments")) return;
        } else {
          exportDocx(dataset, "Payment Records", "CasaVergara-Payments");
        }
        setStatus(
          statusEl,
          dataset.rows.length
            ? `Exported ${dataset.rows.length} payment record(s) as ${format === "pdf" ? "PDF" : "Word (.docx)"}.`
            : `No matching records \u2014 downloaded an empty ${format === "pdf" ? "PDF" : "Word (.docx)"} report.`,
          dataset.rows.length ? "ok" : "muted",
        );
      });
    }

    csvBtn?.addEventListener("click", () => run("docx", csvBtn));
    pdfBtn?.addEventListener("click", () => run("pdf", pdfBtn));
  }
  window.initAdminPaymentsExport = initAdminPaymentsExport;

  function initFilteredReportButtons({ buttonSelector, mainBtnId, statusId, fromId, toId, statusFilterId, roomId }) {
    const statusEl = document.getElementById(statusId);
    const fromEl = document.getElementById(fromId);
    const toEl = document.getElementById(toId);
    const statusFilterEl = document.getElementById(statusFilterId);
    const roomEl = document.getElementById(roomId);

    function populateRoomOptions() {
      if (!roomEl) return;
      const cur = roomEl.value;
      const names = new Set();
      getReservations().forEach((r) => {
        const n = (r.roomName || r.eventType || "").trim();
        if (n) names.add(n);
      });
      roomEl.innerHTML = `<option value="all">All</option>`;
      [...names]
        .sort((a, b) => a.localeCompare(b))
        .forEach((name) => {
          const opt = document.createElement("option");
          opt.value = name;
          opt.textContent = name;
          roomEl.appendChild(opt);
        });
      if ([...names].includes(cur)) roomEl.value = cur;
    }
    populateRoomOptions();

    document.addEventListener("cvr-reservations-synced", populateRoomOptions);

    function currentFilters() {
      return {
        from: fromEl?.value || "",
        to: toEl?.value || "",
        status: statusFilterEl?.value || "all",
        room: roomEl?.value || "all",
      };
    }

    function runReport(reportType, format, btn) {
      withBusy(btn, () => {
        const records = filterRecords(getReservations(), currentFilters());
        const dataset = buildDataset(records, reportType);
        const label = reportType.charAt(0).toUpperCase() + reportType.slice(1);
        const formatLabel = format === "pdf" ? "PDF" : "Word (.docx)";

        if (format === "pdf") {
          if (!exportPdf(dataset, `${label} Report`, `CasaVergara-${label}Report`)) return;
        } else {
          exportDocx(dataset, `${label} Report`, `CasaVergara-${label}Report`);
        }
        setStatus(
          statusEl,
          dataset.rows.length
            ? `Exported ${dataset.rows.length} ${reportType} record(s) as ${formatLabel}.`
            : `No records matched \u2014 downloaded an empty ${formatLabel} report.`,
          dataset.rows.length ? "ok" : "muted",
        );
      });
    }

    document.querySelectorAll(buttonSelector).forEach((btn) => {
      btn.addEventListener("click", () => {
        const fmt = btn.dataset.format === "pdf" ? "pdf" : "docx";
        runReport(btn.dataset.report || "reservation", fmt, btn);
      });
    });

    if (mainBtnId) {
      const mainBtn = document.getElementById(mainBtnId);
      mainBtn?.addEventListener("click", () => runReport("reservation", "docx", mainBtn));
    }
  }

  function initReportsPageReportDownloads() {
    initFilteredReportButtons({
      buttonSelector: ".rep-report-btn",
      mainBtnId: "repReportExportMainBtn",
      statusId: "repReportStatus",
      fromId: "repReportDateFrom",
      toId: "repReportDateTo",
      statusFilterId: "repReportPayStatus",
      roomId: "repReportRoom",
    });
  }
  window.initReportsPageReportDownloads = initReportsPageReportDownloads;

  function initDashboardReportDownloads() {
    initFilteredReportButtons({
      buttonSelector: ".dash-report-btn",
      statusId: "dashReportStatus",
      fromId: "dashReportDateFrom",
      toId: "dashReportDateTo",
      statusFilterId: "dashReportPayStatus",
      roomId: "dashReportRoom",
    });
  }
  window.initDashboardReportDownloads = initDashboardReportDownloads;

  window.CVRAdminExport = { downloadReservationPageExport, buildDataset, exportCsv, exportDocx, exportPdf };
})();
