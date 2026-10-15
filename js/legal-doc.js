

(function () {
  const TYPE_LABELS = {
    room: "Room Booking Terms",
    event: "Event Booking Terms",
    general: "General Terms",
    privacy: "Privacy Policy",
  };

  function fmtDate(raw) {
    if (!raw) return "";
    const d = new Date(String(raw).replace(" ", "T"));
    if (Number.isNaN(d.getTime())) return raw;
    return d.toLocaleDateString(undefined, { month: "long", day: "numeric", year: "numeric" });
  }

  function sectionsHtml(sections) {
    return sections
      .map((s, i) => `
        <div class="cvr-legal-section">
          <span class="cvr-legal-index">${i + 1}</span>
          <div>
            ${s.title ? `<h2 class="h5 fw-bold mb-2">${escapeHtml(s.title)}</h2>` : ""}
            <p class="text-muted-brand mb-0" style="white-space: pre-line;">${escapeHtml(s.body)}</p>
          </div>
        </div>
      `)
      .join("");
  }

  async function loadLegalDoc(type, containerId, titleOverride) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div><p class="mt-3 mb-0">Loading current document\u2026</p></div>`;

    const { ok, body } = await cvrApiGet(`content/terms.php?type=${encodeURIComponent(type)}`);

    if (!ok || !body?.success) {
      container.innerHTML = `<div class="alert alert-warning">We couldn't load this document right now. Please refresh, or contact us if the issue continues.</div>`;
      return;
    }

    const data = body.data;
    const title = titleOverride || TYPE_LABELS[data.bookingType] || "Terms";

    container.innerHTML = `
      <div class="cvr-legal-card">
        <div class="cvr-legal-head">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge cvr-legal-badge">Version ${escapeHtml(data.version)}</span>
            <span class="badge cvr-legal-badge cvr-legal-badge-muted">Effective ${escapeHtml(fmtDate(data.effectiveDate))}</span>
          </div>
          <div class="cvr-legal-title">${escapeHtml(title)}</div>
          <p class="text-muted-brand mb-0">Please review the sections below carefully.</p>
        </div>
        <div class="cvr-legal-body">
          ${sectionsHtml(data.sections)}
        </div>
      </div>
    `;
  }

  function initLegalModals() {
    document.querySelectorAll("[data-cvr-legal-modal]").forEach((modal) => {
      const type = modal.getAttribute("data-cvr-legal-modal");
      const container = modal.querySelector("[data-cvr-legal-body]");
      if (!type || !container) return;
      if (!container.id) container.id = `cvrLegalModalBody-${type}-${Math.random().toString(36).slice(2, 8)}`;

      modal.addEventListener("show.bs.modal", () => {
        if (modal.dataset.cvrLoaded === "1") return;
        modal.dataset.cvrLoaded = "1";
        loadLegalDoc(type, container.id).then(() => {
          container.querySelector(".cvr-legal-card")?.classList.add("cvr-legal-compact");
        });
      });
    });
  }

  window.cvrLoadLegalDoc = loadLegalDoc;
  window.cvrInitLegalModals = initLegalModals;
})();
