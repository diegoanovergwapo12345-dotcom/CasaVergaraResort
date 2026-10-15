
function cvrChatFormatTime(raw) {
  if (!raw) return "";
  const d = new Date(String(raw).replace(" ", "T"));
  if (Number.isNaN(d.getTime())) return String(raw);
  return d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function cvrChatTopicLabel(id, topics) {
  const found = (topics || []).find((t) => t.id === id);
  if (found) return found.label;
  const fallback = {
    room_availability: "Room availability",
    event_bookings: "Event bookings",
    reservation_details: "Reservation details",
    requested_changes: "Requested changes",
    payment_verification: "Payment verification",
    other: "Other inquiry",
  };
  return fallback[id] || "";
}

function cvrChatInitials(name) {
  const parts = String(name || "").trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "?";
  const first = Array.from(parts[0])[0] || "";
  const last = parts.length > 1 ? Array.from(parts[parts.length - 1])[0] || "" : "";
  return (first + last).toUpperCase() || "?";
}

/** Round profile picture; falls back to coloured initials when there is no photo (or it fails to load). */
function cvrChatAvatarMarkup(who) {
  const isStaff = who.senderRole === "staff";
  const name = who.senderLabel || (isStaff ? "Resort team" : "Guest");
  const hue = (Number(who.senderId || 0) * 47) % 360;
  const img = who.avatarUrl
    ? `<img src="${escapeHtml(who.avatarUrl)}" alt="" loading="lazy" onerror="this.remove()">`
    : "";
  return `<span class="cvr-chat-avatar ${isStaff ? "is-staff" : ""}" style="--av-hue:${hue}" title="${escapeHtml(name)}">${escapeHtml(cvrChatInitials(name))}${img}</span>`;
}

const CVR_CHAT_MAX_FILE_BYTES = 25 * 1024 * 1024; // 25 MB

function cvrChatFormatSize(bytes) {
  const n = Number(bytes) || 0;
  if (n >= 1024 * 1024) return (n / (1024 * 1024)).toFixed(n >= 10 * 1024 * 1024 ? 0 : 1) + " MB";
  if (n >= 1024) return Math.round(n / 1024) + " KB";
  return n + " B";
}

function cvrChatFileIcon(name) {
  const ext = String(name || "").split(".").pop().toLowerCase();
  if (ext === "pdf") return "bi-file-earmark-pdf";
  if (["doc", "docx", "odt", "rtf", "txt"].includes(ext)) return "bi-file-earmark-text";
  if (["xls", "xlsx", "csv", "ods"].includes(ext)) return "bi-file-earmark-spreadsheet";
  if (["ppt", "pptx", "odp"].includes(ext)) return "bi-file-earmark-slides";
  if (["zip", "rar", "7z", "tar", "gz"].includes(ext)) return "bi-file-earmark-zip";
  if (["mp3", "wav", "m4a", "ogg"].includes(ext)) return "bi-file-earmark-music";
  if (["mp4", "mov", "avi", "mkv", "webm"].includes(ext)) return "bi-file-earmark-play";
  return "bi-file-earmark";
}

/** Uploads a file with a progress callback (fetch cannot report upload progress). */
function cvrChatUploadFile(file, onProgress) {
  return new Promise((resolve) => {
    const fd = new FormData();
    fd.append("file", file);
    const xhr = new XMLHttpRequest();
    xhr.open("POST", `${cvrApiBase()}/chat/upload.php`);
    xhr.withCredentials = true;
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable && onProgress) onProgress(Math.round((e.loaded / e.total) * 100));
    };
    xhr.onload = () => {
      let body;
      try { body = JSON.parse(xhr.responseText); } catch { body = { success: false, message: "Unexpected server response. The file may be larger than the server allows." }; }
      resolve(body);
    };
    xhr.onerror = () => resolve({ success: false, message: "Network error while uploading." });
    xhr.send(fd);
  });
}

/** "Sent" / "Seen" tick shown under your own messages. */
function cvrChatReceiptMarkup(m) {
  const seen = !!m.seen;
  const title = seen ? `Seen${m.readAt ? " " + cvrChatFormatTime(m.readAt) : ""}` : "Sent";
  return ` <span class="cvr-chat-receipt ${seen ? "is-seen" : ""}" title="${escapeHtml(title)}"><i class="bi ${seen ? "bi-check2-all" : "bi-check2"}"></i> ${seen ? "Seen" : "Sent"}</span>`;
}

/** Ticks off your own messages up to seenUpTo.id. Returns true when something changed. */
function cvrChatApplySeen(messages, seenUpTo, mineRole) {
  if (!seenUpTo || !seenUpTo.id) return false;
  let changed = false;
  messages.forEach((m) => {
    if (m.senderRole === mineRole && !m.seen && m.id <= seenUpTo.id) {
      m.seen = true;
      m.readAt = seenUpTo.at || m.readAt || null;
      changed = true;
    }
  });
  return changed;
}

/** Small in-panel "are you sure?" box. host must be position: relative / absolute. */
function cvrChatConfirm(host, { title, text, confirmLabel = "Delete", danger = true }) {
  return new Promise((resolve) => {
    host.querySelectorAll(".cvr-chat-confirm").forEach((n) => n.remove());
    const ov = document.createElement("div");
    ov.className = "cvr-chat-confirm";
    ov.innerHTML = `<div class="cvr-chat-confirm-box" role="alertdialog" aria-modal="true">
      <h3>${escapeHtml(title)}</h3>
      <p>${escapeHtml(text)}</p>
      <div class="cvr-chat-confirm-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-r="0">Cancel</button>
        <button type="button" class="btn btn-sm ${danger ? "btn-danger" : "btn-brand"}" data-r="1">${escapeHtml(confirmLabel)}</button>
      </div></div>`;
    const done = (v) => { ov.remove(); resolve(v); };
    ov.addEventListener("click", (e) => {
      const b = e.target.closest("[data-r]");
      if (b) done(b.dataset.r === "1");
      else if (e.target === ov) done(false);
    });
    host.appendChild(ov);
    ov.querySelector('[data-r="0"]').focus();
  });
}

/** In-panel choice box (e.g. "Delete for me" / "Delete for everyone"). Resolves to the chosen value or null. */
function cvrChatChoose(host, { title, text, options }) {
  return new Promise((resolve) => {
    host.querySelectorAll(".cvr-chat-confirm").forEach((n) => n.remove());
    const ov = document.createElement("div");
    ov.className = "cvr-chat-confirm";
    ov.innerHTML = `<div class="cvr-chat-confirm-box" role="dialog" aria-modal="true">
      <h3>${escapeHtml(title)}</h3>
      ${text ? `<p>${escapeHtml(text)}</p>` : ""}
      <div class="cvr-chat-choices">
        ${options.map((o) => `<button type="button" class="cvr-chat-choice ${o.danger ? "is-danger" : ""}" data-v="${escapeHtml(o.value)}">
          <strong>${escapeHtml(o.label)}</strong><span>${escapeHtml(o.desc || "")}</span></button>`).join("")}
      </div>
      <div class="cvr-chat-confirm-actions"><button type="button" class="btn btn-sm btn-outline-secondary" data-cancel="1">Cancel</button></div>
    </div>`;
    const done = (v) => { ov.remove(); resolve(v); };
    ov.addEventListener("click", (e) => {
      const c = e.target.closest("[data-v]");
      if (c) return done(c.dataset.v);
      if (e.target.closest("[data-cancel]") || e.target === ov) done(null);
    });
    host.appendChild(ov);
  });
}

/** Marks messages the sender unsent (ids from the server). Returns true when something changed. */
function cvrChatApplyDeleted(messages, ids) {
  if (!ids || !ids.length) return false;
  const set = new Set(ids.map(Number));
  let changed = false;
  messages.forEach((m) => {
    if (!m.deleted && set.has(Number(m.id))) {
      m.deleted = true;
      m.body = "";
      m.imageUrl = null;
      m.fileUrl = null;
      m.fileName = null;
      changed = true;
    }
  });
  return changed;
}

/**
 * Messenger-style message options. Hover a message and press the three dots:
 *   Copy  /  Remove -> "Unsend for everyone" (your own messages) or "Remove for you".
 *   container   - the element that holds the rendered rows
 *   host        - position:relative element the dialogs are drawn inside
 *   getMessage  - (id) => message object
 *   canUnsend   - (message) => boolean
 *   onDelete    - async (message, scope) => void   (scope: "me" | "everyone")
 */
function cvrChatBindMessageActions(container, { host, getMessage, canUnsend, onDelete }) {
  if (!container || container.dataset.cvrActions) return;
  container.dataset.cvrActions = "1";

  let menu = null;
  const closeMenu = () => { if (menu) { menu.remove(); menu = null; } };

  const openMenu = (btn, m) => {
    closeMenu();
    menu = document.createElement("div");
    menu.className = "cvr-chat-menu";
    menu.innerHTML = `
      ${m.body ? `<button type="button" data-act="copy"><i class="bi bi-clipboard"></i> Copy</button>` : ""}
      <button type="button" data-act="remove" class="is-danger"><i class="bi bi-trash3"></i> Remove</button>`;
    document.body.appendChild(menu);
    const r = btn.getBoundingClientRect();
    const mw = menu.offsetWidth;
    const mh = menu.offsetHeight;
    let left = Math.min(Math.max(8, r.left + r.width / 2 - mw / 2), window.innerWidth - mw - 8);
    let top = r.bottom + 6;
    if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 6);
    menu.style.left = left + "px";
    menu.style.top = top + "px";

    menu.addEventListener("click", async (e) => {
      const act = e.target.closest("[data-act]")?.dataset.act;
      if (!act) return;
      closeMenu();
      if (act === "copy") {
        try { await navigator.clipboard.writeText(m.body); if (typeof cvrToast === "function") cvrToast("Copied.", "success"); } catch {}
        return;
      }
      const options = [];
      if (canUnsend(m)) {
        options.push({ value: "everyone", label: "Unsend for everyone", desc: "Everyone in this chat will see that you unsent a message. Any photo or file is deleted.", danger: true });
      }
      options.push({ value: "me", label: "Remove for you", desc: "Removes it from your view only. Others in the chat can still see it." });
      const scope = await cvrChatChoose(host, { title: "Remove this message?", options });
      if (scope) await onDelete(m, scope);
    });
  };

  container.addEventListener("click", (e) => {
    const btn = e.target.closest(".cvr-chat-msg-more");
    if (!btn) return;
    e.stopPropagation();
    const m = getMessage(Number(btn.dataset.msgId));
    if (m && !m.deleted) openMenu(btn, m);
  });
  document.addEventListener("click", (e) => { if (menu && !menu.contains(e.target)) closeMenu(); });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeMenu(); });
  container.addEventListener("scroll", closeMenu, { passive: true });
}

const CVR_EMOJI_ONLY_RE = /^[\p{Extended_Pictographic}\uFE0F\u200D\u{1F3FB}-\u{1F3FF}\s]+$/u;

/**
 * Renders chat rows: [profile picture] [bubble].
 * opts.mineRole  - whose messages sit on the right ("customer" for guests, "staff" in the admin inbox)
 * opts.labelFor  - optional (message, mine) => label text
 */
function cvrChatRenderBubbles(messages, opts = {}) {
  if (!messages.length) {
    return `<p class="small text-muted-brand mb-0 px-1">Send us a message for reservation inquiries and concerns. Our team will respond as soon as available.</p>`;
  }
  const mineRole = opts.mineRole || "customer";
  return messages
    .map((m) => {
      const mine = m.senderRole === mineRole;
      const side = mine ? "guest" : "staff";
      const defaultWho = mineRole === "customer"
        ? (mine ? "You" : m.senderLabel || "Resort team")
        : (m.senderLabel || (m.senderRole === "staff" ? "Resort team" : "Guest"));
      const who = escapeHtml(typeof opts.labelFor === "function" ? opts.labelFor(m, mine) : defaultWho);
      if (m.deleted) {
        const plainName = m.senderLabel || (m.senderRole === "staff" ? "Resort team" : "Guest");
        return `<div class="cvr-chat-row cvr-chat-row-${side}" data-msg-id="${m.id}">
          ${cvrChatAvatarMarkup(m)}
          <div class="cvr-chat-bubble cvr-chat-bubble-${side} is-unsent">
            <i class="bi bi-slash-circle me-1"></i>${escapeHtml(mine && mineRole === "customer" ? "You unsent a message" : `${plainName} unsent a message`)}
          </div>
        </div>`;
      }
      const body = String(m.body || "");
      const emojiOnly = !m.imageUrl && body && CVR_EMOJI_ONLY_RE.test(body) && Array.from(body.replace(/\s/g, "")).length <= 6;
      const text = body
        ? `<div class="cvr-chat-text ${emojiOnly ? "is-emoji-only" : ""}">${escapeHtml(body).replace(/\n/g, "<br>")}</div>`
        : "";
      const image = m.imageUrl
        ? `<a href="${escapeHtml(m.imageUrl)}" target="_blank" rel="noopener" class="cvr-chat-img-link"><img class="cvr-chat-img" src="${escapeHtml(m.imageUrl)}" alt="Shared GIF or photo" loading="lazy"></a>`
        : "";
      const fileCard = m.fileUrl
        ? `<a class="cvr-chat-file" href="${escapeHtml(m.fileUrl)}" download="${escapeHtml(m.fileName || "file")}" target="_blank" rel="noopener">
            <span class="cvr-chat-file-icon"><i class="bi ${cvrChatFileIcon(m.fileName)}"></i></span>
            <span class="cvr-chat-file-info"><span class="cvr-chat-file-name">${escapeHtml(m.fileName || "File")}</span><span class="cvr-chat-file-size">${escapeHtml(cvrChatFormatSize(m.fileSize))} · Download</span></span>
          </a>`
        : "";
      const attachOnly = (m.imageUrl || m.fileUrl) && !body;
      return `<div class="cvr-chat-row cvr-chat-row-${side}" data-msg-id="${m.id}">
        ${cvrChatAvatarMarkup(m)}
        <div class="cvr-chat-bubble cvr-chat-bubble-${side} ${attachOnly ? "has-image-only" : ""} ${emojiOnly ? "is-emoji-only-bubble" : ""}">
          <div class="cvr-chat-bubble-who">${who}</div>
          ${text}${image}${fileCard}
          <div class="cvr-chat-bubble-meta">${escapeHtml(cvrChatFormatTime(m.createdAt))}${mine ? cvrChatReceiptMarkup(m) : ""}</div>
        </div>
        <button type="button" class="cvr-chat-msg-more" data-msg-id="${m.id}" title="More" aria-label="Message options"><i class="bi bi-three-dots-vertical"></i></button>
      </div>`;
    })
    .join("");
}

/** Scroll to the newest message, and again once any GIF/photo has finished loading (it changes the height). */
function cvrChatScrollToEnd(el) {
  if (!el) return;
  el.scrollTop = el.scrollHeight;
  el.querySelectorAll("img.cvr-chat-img").forEach((img) => {
    if (!img.complete) {
      img.addEventListener("load", () => { el.scrollTop = el.scrollHeight; }, { once: true });
    }
  });
}

const CVR_EMOJI_GROUPS = [
  ["Smileys", "😀 😃 😄 😁 😆 😅 😂 🤣 🙂 😉 😊 😇 🥰 😍 🤩 😘 😋 😎 🤗 🤔 😐 😏 😴 😮 😢 😭 😡 🥳 😱 😬 🙄 😌"],
  ["Gestures", "👍 👎 👌 ✌️ 🤞 🤝 🙏 👏 🙌 💪 👋 🤙 ☝️ 👀"],
  ["Hearts", "❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💖 💕 💯 ✨ 🔥 ⭐ 🎉"],
  ["Resort", "🏖️ 🏝️ 🌴 🌊 ☀️ 🌅 🏊 🍹 🍽️ 🍳 🛏️ 🛎️ 🔑 🚗 ✈️ 📅 📍 📞 🎂 🎈 🎁 💍 🥂 🍾"],
  ["Symbols", "✅ ❌ ⚠️ ❓ ❗ 💬 💳 💰 🧾 📷 🕒 🆗"],
];

/**
 * Adds emoji / GIF / photo buttons above the message box.
 *   form       - the <form> that holds the textarea
 *   input      - the <textarea>
 *   sendImage  - async (imageUrl) => boolean ; posts a GIF/photo message
 */
function cvrChatMountTools({ form, input, sendImage }) {
  if (!form || !input || form.dataset.cvrToolsMounted) return;
  form.dataset.cvrToolsMounted = "1";

  const wrap = document.createElement("div");
  wrap.className = "cvr-chat-tools";
  wrap.innerHTML = `
    <button type="button" class="cvr-chat-tool" data-tool="emoji" title="Emoji" aria-label="Emoji"><i class="bi bi-emoji-smile"></i></button>
    <button type="button" class="cvr-chat-tool cvr-chat-tool-gif" data-tool="gif" title="Search GIFs" aria-label="Search GIFs">GIF</button>
    <button type="button" class="cvr-chat-tool" data-tool="photo" title="Attach a photo or file (up to 25 MB)" aria-label="Attach a photo or file"><i class="bi bi-paperclip"></i></button>
    <span class="cvr-chat-tool-hint">Max 25 MB</span>
    <input type="file" class="d-none">
    <div class="cvr-chat-pending d-none">
      <div class="cvr-chat-pending-thumb"></div>
      <div class="cvr-chat-pending-info">
        <div class="cvr-chat-pending-name"></div>
        <div class="cvr-chat-pending-size"></div>
        <div class="cvr-chat-upload-bar d-none"><span></span></div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-pending="cancel">Cancel</button>
      <button type="button" class="btn btn-sm btn-brand" data-pending="send"><i class="bi bi-send-fill me-1"></i>Send</button>
    </div>
    <div class="cvr-chat-picker d-none" data-picker="emoji"></div>
    <div class="cvr-chat-picker d-none" data-picker="gif">
      <div class="cvr-gif-search"><input type="search" class="form-control form-control-sm" placeholder="Search GIFs…" aria-label="Search GIFs"></div>
      <div class="cvr-gif-grid"></div>
      <div class="cvr-gif-foot">Powered by GIPHY</div>
    </div>`;

  const anchor = form.querySelector(".cvr-chat-form") || input.closest(".input-group") || input;
  anchor.parentNode.insertBefore(wrap, anchor);

  const emojiPicker = wrap.querySelector('[data-picker="emoji"]');
  const gifPicker = wrap.querySelector('[data-picker="gif"]');
  const gifGrid = gifPicker.querySelector(".cvr-gif-grid");
  const gifSearch = gifPicker.querySelector("input");
  const fileInput = wrap.querySelector('input[type="file"]');

  emojiPicker.innerHTML = CVR_EMOJI_GROUPS.map(([title, list]) =>
    `<div class="cvr-emoji-title">${title}</div>` +
    list.split(" ").map((e) => `<button type="button" class="cvr-emoji-btn" data-emoji="${e}">${e}</button>`).join("")
  ).join("");
  emojiPicker.classList.add("cvr-emoji-grid");

  const closePickers = () => {
    emojiPicker.classList.add("d-none");
    gifPicker.classList.add("d-none");
  };

  const insertEmoji = (emoji) => {
    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? input.value.length;
    input.setRangeText(emoji, start, end, "end");
    input.focus();
  };

  let gifTimer = null;
  let gifLoadedOnce = false;
  const setGifMessage = (html) => { gifGrid.innerHTML = `<p class="cvr-gif-note">${html}</p>`; };

  const loadGifs = async (query) => {
    setGifMessage("Loading…");
    const { body } = await cvrApiGet(`chat/gif-search.php?q=${encodeURIComponent(query)}`);
    if (!body?.success) {
      setGifMessage(escapeHtml(body?.message || "Could not load GIFs right now."));
      return;
    }
    if (body.data?.configured === false) {
      setGifMessage("GIF search isn't switched on yet. You can still send a GIF or photo from your device with the <i class=\"bi bi-image\"></i> button.");
      return;
    }
    const results = body.data?.results || [];
    if (!results.length) {
      setGifMessage("No GIFs found. Try another word.");
      return;
    }
    gifGrid.innerHTML = results
      .map((g) => `<button type="button" class="cvr-gif-item" data-url="${escapeHtml(g.url)}" title="${escapeHtml(g.title || "GIF")}"><img src="${escapeHtml(g.previewUrl)}" alt="${escapeHtml(g.title || "GIF")}" loading="lazy"></button>`)
      .join("");
  };

  const busy = (on) => wrap.classList.toggle("is-busy", on);

  const sendPicked = async (imageUrl) => {
    busy(true);
    try { await sendImage(imageUrl); } finally { busy(false); }
  };

  wrap.addEventListener("click", async (e) => {
    const tool = e.target.closest("[data-tool]");
    if (tool) {
      const kind = tool.dataset.tool;
      if (kind === "photo") {
        closePickers();
        fileInput.click();
        return;
      }
      const picker = kind === "emoji" ? emojiPicker : gifPicker;
      const wasOpen = !picker.classList.contains("d-none");
      closePickers();
      if (!wasOpen) {
        picker.classList.remove("d-none");
        if (kind === "gif") {
          if (!gifLoadedOnce) { gifLoadedOnce = true; loadGifs(""); }
          gifSearch.focus();
        }
      }
      return;
    }
    const emojiBtn = e.target.closest("[data-emoji]");
    if (emojiBtn) {
      insertEmoji(emojiBtn.dataset.emoji);
      return;
    }
    const gifBtn = e.target.closest(".cvr-gif-item");
    if (gifBtn) {
      closePickers();
      await sendPicked(gifBtn.dataset.url);
    }
  });

  gifSearch.addEventListener("input", () => {
    clearTimeout(gifTimer);
    gifTimer = setTimeout(() => loadGifs(gifSearch.value.trim()), 350);
  });
  gifSearch.addEventListener("keydown", (e) => { if (e.key === "Enter") e.preventDefault(); });

  const pendingEl = wrap.querySelector(".cvr-chat-pending");
  const pendingThumb = wrap.querySelector(".cvr-chat-pending-thumb");
  const pendingName = wrap.querySelector(".cvr-chat-pending-name");
  const pendingSize = wrap.querySelector(".cvr-chat-pending-size");
  const pendingBar = wrap.querySelector(".cvr-chat-upload-bar");
  const pendingBarFill = pendingBar.querySelector("span");
  const pendingSendBtn = wrap.querySelector('[data-pending="send"]');
  const pendingCancelBtn = wrap.querySelector('[data-pending="cancel"]');
  let pendingFile = null;
  let pendingUrl = null;
  let sending = false;

  const clearPending = () => {
    if (pendingUrl) { URL.revokeObjectURL(pendingUrl); pendingUrl = null; }
    pendingFile = null;
    delete form.dataset.cvrPending;
    pendingEl.classList.add("d-none");
    pendingBar.classList.add("d-none");
    pendingBarFill.style.width = "0%";
    pendingSendBtn.disabled = false;
    pendingCancelBtn.disabled = false;
    input.placeholder = input.dataset.cvrPlaceholder || input.placeholder;
  };

  // Step 1: choosing a file only shows a preview. Nothing is uploaded or sent yet.
  const handleFile = (file) => {
    if (!file) return;
    if (file.size > CVR_CHAT_MAX_FILE_BYTES) {
      if (typeof cvrToast === "function") cvrToast("Files must be smaller than 25 MB.", "danger");
      return;
    }
    if (file.size < 1) {
      if (typeof cvrToast === "function") cvrToast("That file is empty.", "danger");
      return;
    }
    closePickers();
    clearPending();
    pendingFile = file;
    form.dataset.cvrPending = "1";
    if (file.type && file.type.startsWith("image/") && file.type !== "image/svg+xml") {
      pendingUrl = URL.createObjectURL(file);
      pendingThumb.innerHTML = `<img src="${pendingUrl}" alt="">`;
    } else {
      pendingThumb.innerHTML = `<i class="bi ${cvrChatFileIcon(file.name)}"></i>`;
    }
    pendingName.textContent = file.name;
    pendingSize.textContent = `${cvrChatFormatSize(file.size)} · Ready to send. Add a message if you like.`;
    if (!input.dataset.cvrPlaceholder) input.dataset.cvrPlaceholder = input.placeholder;
    input.placeholder = "Add a message (optional)…";
    pendingEl.classList.remove("d-none");
    input.focus();
  };

  // Step 2: only after the person confirms does the file upload and send.
  const confirmSendFile = async () => {
    if (!pendingFile || sending) return;
    sending = true;
    pendingSendBtn.disabled = true;
    pendingCancelBtn.disabled = true;
    pendingBar.classList.remove("d-none");
    pendingBarFill.style.width = "0%";
    try {
      const body = await cvrChatUploadFile(pendingFile, (pct) => { pendingBarFill.style.width = pct + "%"; });
      if (!body?.success) {
        if (typeof cvrToast === "function") cvrToast(body?.message || "Could not upload that file.", "danger");
        pendingSendBtn.disabled = false;
        pendingCancelBtn.disabled = false;
        pendingBar.classList.add("d-none");
        return;
      }
      const text = (input.value || "").trim();
      const payload = body.data.kind === "file"
        ? { fileUrl: body.data.fileUrl, fileName: body.data.fileName, text }
        : { imageUrl: body.data.imageUrl, text };
      const ok = await sendImage(payload);
      if (ok) {
        input.value = "";
        input.style.height = "auto";
        clearPending();
      } else {
        pendingSendBtn.disabled = false;
        pendingCancelBtn.disabled = false;
        pendingBar.classList.add("d-none");
      }
    } finally {
      sending = false;
    }
  };

  pendingSendBtn.addEventListener("click", confirmSendFile);
  pendingCancelBtn.addEventListener("click", clearPending);
  // Pressing Enter / the send arrow while a file is waiting confirms it too.
  form.addEventListener("submit", (e) => {
    if (form.dataset.cvrPending) {
      e.preventDefault();
      confirmSendFile();
    }
  });

  fileInput.addEventListener("change", () => {
    const file = fileInput.files && fileInput.files[0];
    fileInput.value = "";
    handleFile(file);
  });

  // Paste a screenshot / drag a file straight onto the message box.
  input.addEventListener("paste", (e) => {
    const f = Array.from(e.clipboardData?.files || [])[0];
    if (f) { e.preventDefault(); handleFile(f); }
  });
  const dropTarget = form.closest(".cvr-chat-panel, .live-chat-main") || form;
  window.cvrChatClearPending = clearPending;
  ["dragenter", "dragover"].forEach((ev) => dropTarget.addEventListener(ev, (e) => {
    if (e.dataTransfer && Array.from(e.dataTransfer.types || []).includes("Files")) {
      e.preventDefault();
      dropTarget.classList.add("is-dragover");
    }
  }));
  ["dragleave", "drop"].forEach((ev) => dropTarget.addEventListener(ev, (e) => {
    if (ev === "drop") {
      const f = e.dataTransfer?.files?.[0];
      if (f) { e.preventDefault(); handleFile(f); }
    }
    dropTarget.classList.remove("is-dragover");
  }));

  document.addEventListener("click", (e) => {
    if (!wrap.contains(e.target)) closePickers();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closePickers();
  });
}

window.cvrChatMountTools = cvrChatMountTools;

function initLiveChatWidget() {
  if (window.__cvrLiveChatBound) return;
  if (location.pathname.includes("/pages/admin/")) return;

  const session = typeof getCustomerSession === "function" ? getCustomerSession() : null;
  if (session && typeof isStaffSession === "function" && isStaffSession(session)) return;

  window.__cvrLiveChatBound = true;

  const dock = document.createElement("div");
  dock.id = "cvrLiveChatDock";
  dock.className = "cvr-live-chat-dock";
  dock.innerHTML = `
    <div id="cvrLiveChatPanel" class="cvr-chat-panel d-none" role="dialog" aria-labelledby="cvrLiveChatTitle">
      <div class="cvr-chat-panel-head">
        <span class="cvr-chat-head-icon"><i class="bi bi-headset"></i></span>
        <div class="cvr-chat-head-text">
          <h2 class="cvr-chat-head-title" id="cvrLiveChatTitle">Casa Vergara Live Chat</h2>
          <p class="cvr-chat-head-status" id="cvrLiveChatStatus"><span class="cvr-chat-status-dot is-offline"></span> Checking availability…</p>
        </div>
        <button type="button" class="cvr-chat-head-btn d-none" id="cvrLiveChatDelete" title="Delete chat" aria-label="Delete chat"><i class="bi bi-trash3"></i></button>
        <button type="button" class="btn-close" id="cvrLiveChatClose" aria-label="Close chat"></button>
      </div>
      <div id="cvrLiveChatUnavailable" class="cvr-chat-unavailable d-none" role="status"></div>
      <div id="cvrLiveChatAuth" class="cvr-chat-auth-gate d-none">
        <p class="small text-muted-brand mb-3">Sign in to send us a message for reservation inquiries and concerns. Our team will respond as soon as available.</p>
        <a class="btn btn-brand w-100" id="cvrLiveChatLoginLink" href="#">Log in to chat</a>
      </div>
      <div id="cvrLiveChatThreadWrap" class="d-none flex-column flex-grow-1">
        <div id="cvrLiveChatMessages" class="cvr-chat-messages"></div>
        <form id="cvrLiveChatForm" class="cvr-chat-compose">
          <div class="cvr-chat-form input-group">
            <textarea class="form-control" id="cvrLiveChatInput" rows="1" maxlength="2000" placeholder="Type your message…"></textarea>
            <button class="btn btn-brand" type="submit" id="cvrLiveChatSend" aria-label="Send"><i class="bi bi-send-fill"></i></button>
          </div>
        </form>
      </div>
    </div>
    <button type="button" class="btn btn-brand cvr-chat-fab" id="cvrLiveChatFab" aria-label="Live Chat" title="Live Chat">
      <i class="bi bi-chat-dots-fill"></i>
      <span id="cvrLiveChatBadge" class="cvr-chat-fab-badge d-none">0</span>
    </button>
  `;
  document.body.appendChild(dock);

  const panel = document.getElementById("cvrLiveChatPanel");
  const fab = document.getElementById("cvrLiveChatFab");
  const closeBtn = document.getElementById("cvrLiveChatClose");
  const statusEl = document.getElementById("cvrLiveChatStatus");
  const unavailableEl = document.getElementById("cvrLiveChatUnavailable");
  const authEl = document.getElementById("cvrLiveChatAuth");
  const threadWrap = document.getElementById("cvrLiveChatThreadWrap");
  const messagesEl = document.getElementById("cvrLiveChatMessages");
  const form = document.getElementById("cvrLiveChatForm");
  const input = document.getElementById("cvrLiveChatInput");
  const badge = document.getElementById("cvrLiveChatBadge");
  const deleteBtn = document.getElementById("cvrLiveChatDelete");
  const loginLink = document.getElementById("cvrLiveChatLoginLink");
  if (loginLink) loginLink.href = pageUrl("login.html");

  const state = {
    open: false,
    messages: [],
    lastId: 0,
    userId: session?.id || null,
    pollTimer: null,
    abort: null,
  };

  const setBadge = (n) => {
    if (!badge) return;
    if (n > 0) {
      badge.textContent = n > 9 ? "9+" : String(n);
      badge.classList.remove("d-none");
    } else {
      badge.classList.add("d-none");
    }
  };

  const applyPresence = (presence) => {
    if (!statusEl) return;
    const online = !!presence?.staffOnline;
    statusEl.innerHTML = online
      ? `<span class="cvr-chat-status-dot is-online"></span> We're online · replies in minutes`
      : `<span class="cvr-chat-status-dot is-offline"></span> Offline · leave a message`;
    if (unavailableEl) {
      if (online) {
        unavailableEl.classList.add("d-none");
        unavailableEl.textContent = "";
      } else {
        unavailableEl.classList.remove("d-none");
        unavailableEl.textContent = presence?.unavailableMessage
          || "Send us a message for reservation inquiries and concerns. Our team will respond as soon as available.";
      }
    }
  };

  const renderMessages = () => {
    if (!messagesEl) return;
    messagesEl.innerHTML = cvrChatRenderBubbles(state.messages);
    cvrChatScrollToEnd(messagesEl);
  };

  const mergeMessages = (incoming) => {
    let added = 0;
    incoming.forEach((m) => {
      if (!state.messages.some((x) => x.id === m.id)) {
        state.messages.push(m);
        added += 1;
      }
    });
    state.messages.sort((a, b) => a.id - b.id);
    if (state.messages.length) state.lastId = state.messages[state.messages.length - 1].id;
    return added;
  };

  const loadConversation = async () => {
    const current = typeof getCustomerSession === "function" ? getCustomerSession() : null;
    if (!current) {
      authEl?.classList.remove("d-none");
      deleteBtn?.classList.add("d-none");
      threadWrap?.classList.add("d-none");
      threadWrap?.classList.remove("d-flex");
      const { body } = await cvrApiGet("chat/presence.php");
      if (body?.success) applyPresence(body.data);
      return;
    }
    authEl?.classList.add("d-none");
    deleteBtn?.classList.remove("d-none");
    threadWrap?.classList.remove("d-none");
    threadWrap?.classList.add("d-flex");
    const { body } = await cvrApiGet("chat/conversation.php");
    if (!body?.success) return;
    state.userId = current.id;
    state.messages = body.data.messages || [];
    state.lastId = state.messages.length ? state.messages[state.messages.length - 1].id : 0;
    cvrChatApplySeen(state.messages, body.data.seenUpTo, "customer");
    cvrChatApplyDeleted(state.messages, body.data.deletedIds);
    applyPresence(body.data.presence);
    setBadge(body.data.conversation?.customerUnread || 0);
    renderMessages();
    if (state.open) {
      cvrApiPost("chat/mark-read.php", {}).catch(() => {});
      setBadge(0);
    }
  };

  const stopPoll = () => {
    if (state.abort) {
      try { state.abort.abort(); } catch {}
      state.abort = null;
    }
    if (state.pollTimer) {
      clearTimeout(state.pollTimer);
      state.pollTimer = null;
    }
  };

  const pollOnce = async (wait) => {
    const current = typeof getCustomerSession === "function" ? getCustomerSession() : null;
    if (!current) {
      const { body } = await cvrApiGet("chat/presence.php");
      if (body?.success) applyPresence(body.data);
      return;
    }
    const qs = `chat/poll.php?afterId=${state.lastId || 0}${wait ? "&wait=1" : ""}`;
    const { body } = await cvrApiGet(qs);
    if (!body?.success) return;
    // The resort deleted this conversation: clear it from the screen too.
    if (!Number(body.data.conversationId) && state.messages.length && !(body.data.messages || []).length) {
      state.messages = [];
      state.lastId = 0;
      renderMessages();
    }
    const added = mergeMessages(body.data.messages || []);
    const seenChanged = cvrChatApplySeen(state.messages, body.data.seenUpTo, "customer");
    const delChanged = cvrChatApplyDeleted(state.messages, body.data.deletedIds);
    applyPresence(body.data.presence);
    if (added || seenChanged || delChanged) renderMessages();
    if (!state.open) setBadge(body.data.unread || 0);
    else if ((body.data.messages || []).length) {
      cvrApiPost("chat/mark-read.php", {}).catch(() => {});
      setBadge(0);
    }
  };

  const loopPoll = async () => {
    try {
      await pollOnce(state.open);
    } catch {
      
    }
    state.pollTimer = setTimeout(loopPoll, state.open ? 400 : 8000);
  };

  const openPanel = async () => {
    state.open = true;
    panel?.classList.remove("d-none");
    fab?.setAttribute("aria-expanded", "true");
    await loadConversation();
    if (authEl?.classList.contains("d-none")) input?.focus();
  };

  const closePanel = () => {
    state.open = false;
    panel?.classList.add("d-none");
    fab?.setAttribute("aria-expanded", "false");
  };

  fab?.addEventListener("click", () => {
    if (state.open) closePanel();
    else openPanel();
  });
  closeBtn?.addEventListener("click", closePanel);

  cvrChatBindMessageActions(messagesEl, {
    host: panel,
    getMessage: (id) => state.messages.find((x) => x.id === id),
    canUnsend: (m) => m.senderRole === "customer",
    onDelete: async (m, scope) => {
      const { body } = await cvrApiPost("chat/delete-message.php", { messageId: m.id, scope });
      if (!body?.success) {
        if (typeof cvrToast === "function") cvrToast(body?.message || "Could not remove that message.", "danger");
        return;
      }
      if (scope === "me") state.messages = state.messages.filter((x) => x.id !== m.id);
      else cvrChatApplyDeleted(state.messages, [m.id]);
      renderMessages();
    },
  });

  deleteBtn?.addEventListener("click", async () => {
    const scope = await cvrChatChoose(panel, {
      title: "Delete this chat?",
      options: [
        { value: "me", label: "Delete for me", desc: "Clears it from your chat window. The resort team keeps their copy." },
        { value: "everyone", label: "Delete for everyone", desc: "Permanently deletes the conversation and any files. The resort team will no longer see it either.", danger: true },
      ],
    });
    if (!scope) return;
    const { body } = await cvrApiPost("chat/delete.php", { scope });
    if (!body?.success) {
      if (typeof cvrToast === "function") cvrToast(body?.message || "Could not delete the chat.", "danger");
      return;
    }
    state.messages = [];
    state.lastId = 0;
    renderMessages();
    setBadge(0);
    if (typeof window.cvrChatClearPending === "function") window.cvrChatClearPending();
    if (typeof cvrToast === "function") cvrToast(scope === "everyone" ? "Chat deleted for everyone." : "Chat deleted for you.", "success");
  });

  const sendMessage = async ({ text, imageUrl, fileUrl, fileName }) => {
    const payload = {};
    if (text) payload.message = text;
    if (imageUrl) payload.imageUrl = imageUrl;
    if (fileUrl) { payload.fileUrl = fileUrl; payload.fileName = fileName || ""; }
    const sendBtn = document.getElementById("cvrLiveChatSend");
    if (sendBtn) sendBtn.disabled = true;
    const { body } = await cvrApiPost("chat/send.php", payload);
    if (sendBtn) sendBtn.disabled = false;
    if (!body?.success) {
      if (typeof cvrToast === "function") cvrToast(body?.message || "Could not send message.", "danger");
      return false;
    }
    mergeMessages([body.data.message]);
    applyPresence(body.data.presence);
    renderMessages();
    setBadge(0);
    return true;
  };

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (form.dataset.cvrPending) return; // a file is waiting for confirmation
    const text = (input?.value || "").trim();
    if (!text) return;
    const ok = await sendMessage({ text });
    if (ok && input) { input.value = ""; input.style.height = "auto"; }
  });

  cvrChatMountTools({ form, input, sendImage: (a) => sendMessage(typeof a === "string" ? { imageUrl: a } : a) });

  input?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form?.requestSubmit();
    }
  });
  input?.addEventListener("input", () => {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 110) + "px";
  });

  document.addEventListener("cvr-open-live-chat", () => {
    if (!state.open) openPanel();
  });

  if (location.hash === "#live-chat" || window.__cvrOpenLiveChatPending) {
    window.__cvrOpenLiveChatPending = false;
    openPanel();
  }

  loadConversation().then(loopPoll);
}

window.__cvrLiveChatInit = initLiveChatWidget;
window.initLiveChatWidget = initLiveChatWidget;

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initLiveChatWidget);
} else {
  initLiveChatWidget();
}
