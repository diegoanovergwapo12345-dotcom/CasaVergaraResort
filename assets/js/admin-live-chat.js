
function initAdminLiveChatPage() {
  const listEl = document.getElementById("liveChatList");
  const threadEl = document.getElementById("liveChatThread");
  const emptyEl = document.getElementById("liveChatEmpty");
  const composer = document.getElementById("liveChatComposer");
  const form = document.getElementById("liveChatReplyForm");
  const input = document.getElementById("liveChatReplyInput");
  const searchInput = document.getElementById("liveChatSearch");
  const guestNameEl = document.getElementById("liveChatGuestName");
  const guestMetaEl = document.getElementById("liveChatGuestMeta");
  const deleteBtn = document.getElementById("liveChatDelete");
  const mainEl = document.querySelector(".live-chat-main");
  const presenceEl = document.getElementById("liveChatStaffPresence");
  if (!listEl || !threadEl) return;

  window.__cvrChatIsStaffView = true;

  const state = {
    conversations: [],
    activeId: 0,
    messages: [],
    lastId: 0,
    userId: (typeof getCustomerSession === "function" ? getCustomerSession() : null)?.id || 0,
    topics: [],
  };

  const params = new URLSearchParams(location.search);
  const requestedId = Number(params.get("c") || 0);

  let onlineGuests = 0;
  const applyPresence = (presence) => {
    if (!presenceEl) return;
    const online = !!presence?.staffOnline;
    const guests = onlineGuests > 0
      ? ` · <span class="cvr-chat-status-dot is-online"></span><strong>${onlineGuests}</strong> guest${onlineGuests === 1 ? "" : "s"} online now`
      : ` · No guests online right now`;
    presenceEl.innerHTML = (online
      ? `<span class="cvr-chat-status-dot is-online"></span> You are online. Staff and admins share this inbox.`
      : `<span class="cvr-chat-status-dot is-offline"></span> No team member is marked online right now`) + guests;
  };

  const filteredConversations = () => {
    const q = (searchInput?.value || "").trim().toLowerCase();
    if (!q) return state.conversations;
    return state.conversations.filter((c) => {
      const hay = `${c.customerName || ""} ${c.customerEmail || ""} ${c.lastMessagePreview || ""} ${c.reservationId || ""}`.toLowerCase();
      return hay.includes(q);
    });
  };

  const renderList = () => {
    const items = filteredConversations();
    if (!items.length) {
      listEl.innerHTML = `<p class="small text-muted-brand px-3 py-4 mb-0">No client conversations yet.</p>`;
      return;
    }
    listEl.innerHTML = items
      .map((c) => {
        const active = Number(c.id) === Number(state.activeId);
        const unread = Number(c.staffUnread) > 0;
        const when = c.lastMessageAt ? cvrChatFormatTime(c.lastMessageAt) : "";
        const avatar = cvrChatAvatarMarkup({
          senderRole: "customer",
          senderId: c.customerId,
          senderLabel: c.customerName || "Guest",
          avatarUrl: c.customerAvatarUrl,
        });
        return `<button type="button" class="live-chat-conv ${active ? "is-active" : ""} ${unread ? "is-unread" : ""}" data-id="${c.id}">
          <div class="d-flex align-items-center gap-2">
            <span class="cvr-chat-av-wrap ${c.customerOnline ? "is-online" : ""}" title="${c.customerOnline ? "Online now" : "Offline"}">${avatar}<span class="cvr-chat-av-dot"></span></span>
            <div class="flex-grow-1" style="min-width:0">
              <div class="d-flex justify-content-between gap-2">
                <strong class="text-truncate">${escapeHtml(c.customerName || "Guest")}</strong>
                ${unread ? `<span class="badge text-bg-danger">${c.staffUnread > 9 ? "9+" : c.staffUnread}</span>` : ""}
              </div>
              <div class="small text-muted-brand text-truncate">${escapeHtml(c.lastMessagePreview || "No messages yet")}</div>
              <div class="small text-muted-brand">${c.customerOnline ? `<span class="cvr-online-label">Online</span> · ` : ""}${escapeHtml(when)}${c.reservationId ? " · " + escapeHtml(c.reservationId) : ""}</div>
            </div>
          </div>
        </button>`;
      })
      .join("");
  };

  const renderThread = () => {
    const conv = state.conversations.find((c) => Number(c.id) === Number(state.activeId));
    if (!conv) {
      emptyEl?.classList.remove("d-none");
      composer?.classList.add("d-none");
      deleteBtn?.classList.add("d-none");
      threadEl.innerHTML = "";
      if (guestNameEl) guestNameEl.textContent = "Select a conversation";
      if (guestMetaEl) guestMetaEl.textContent = "Client messages appear here as they arrive.";
      return;
    }
    emptyEl?.classList.add("d-none");
    composer?.classList.remove("d-none");
    deleteBtn?.classList.remove("d-none");
    if (guestNameEl) guestNameEl.textContent = conv.customerName || "Guest";
    if (guestMetaEl) {
      const bits = [conv.customerEmail, conv.reservationId].filter(Boolean);
      const status = conv.customerOnline
        ? `<span class="cvr-chat-status-dot is-online"></span><span class="cvr-online-label">Online now</span>`
        : `<span class="cvr-chat-status-dot is-offline"></span>Offline${conv.customerLastSeen ? " · last seen " + escapeHtml(cvrChatFormatTime(conv.customerLastSeen)) : ""}`;
      guestMetaEl.innerHTML = status + (bits.length ? " · " + escapeHtml(bits.join(" · ")) : "");
    }
    threadEl.innerHTML = !(state.messages || []).length ? "" : cvrChatRenderBubbles(state.messages, {
      mineRole: "staff",
      labelFor: (m) => {
        const staff = m.senderRole === "staff";
        const name = m.senderLabel || (staff ? "Resort team" : conv.customerName || "Guest");
        return staff ? `${name} · Team` : name;
      },
    });
    cvrChatScrollToEnd(threadEl);
  };

  let initialPicked = false;
  const loadInbox = async () => {
    const { body } = await cvrApiGet("chat/inbox.php");
    if (!body?.success) return;
    state.conversations = body.data.conversations || [];
    state.topics = body.data.topics || [];
    onlineGuests = Number(body.data.onlineGuests || 0);
    applyPresence(body.data.presence);
    const stillThere = state.conversations.some((c) => Number(c.id) === Number(state.activeId));
    if (state.activeId && !stillThere) {
      // deleted (by you or another staff member)
      state.activeId = 0;
      state.messages = [];
      state.lastId = 0;
      if (typeof window.cvrChatClearPending === "function") window.cvrChatClearPending();
      renderThread();
    }
    if (!initialPicked) {
      initialPicked = true;
      if (!state.activeId && requestedId) state.activeId = requestedId;
      if (!state.activeId && state.conversations.length) state.activeId = state.conversations[0].id;
    }
    renderList();
    updateSidebarBadge(body.data.unreadCount || 0);
  };

  let lastOnlineSeen = null;
  const loadThread = async (reset) => {
    if (!state.activeId) {
      renderThread();
      return;
    }
    const after = reset ? 0 : state.lastId;
    const { body } = await cvrApiGet(`chat/thread.php?id=${state.activeId}&afterId=${after}`);
    if (!body?.success) return;
    applyPresence(body.data.presence);
    let added = 0;
    if (reset) {
      state.messages = body.data.messages || [];
      added = state.messages.length;
    } else {
      (body.data.messages || []).forEach((m) => {
        if (!state.messages.some((x) => x.id === m.id)) {
          state.messages.push(m);
          added += 1;
        }
      });
    }
    if (state.messages.length) state.lastId = state.messages[state.messages.length - 1].id;
    const seenChanged = cvrChatApplySeen(state.messages, body.data.seenUpTo, "staff");
    const delChanged = cvrChatApplyDeleted(state.messages, body.data.deletedIds);
    const conv = body.data.conversation;
    if (conv) {
      const idx = state.conversations.findIndex((c) => Number(c.id) === Number(conv.id));
      if (idx >= 0) state.conversations[idx] = { ...state.conversations[idx], ...conv };
    }
    const prevOnline = lastOnlineSeen;
    lastOnlineSeen = !!conv?.customerOnline;
    if (reset || added || seenChanged || delChanged || prevOnline !== lastOnlineSeen) renderThread();
    renderList();
    if (reset || added || Number(conv?.staffUnread) > 0) {
      await cvrApiPost("chat/mark-read.php", { conversationId: state.activeId });
    }
  };

  const updateSidebarBadge = (n) => {
    const badge = document.getElementById("adminLiveChatBadge");
    if (!badge) return;
    if (n > 0) {
      badge.textContent = n > 9 ? "9+" : String(n);
      badge.classList.remove("d-none");
    } else badge.classList.add("d-none");
  };

  listEl.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-id]");
    if (!btn) return;
    state.activeId = Number(btn.dataset.id);
    state.lastId = 0;
    state.messages = [];
    const url = new URL(location.href);
    url.searchParams.set("c", String(state.activeId));
    history.replaceState({}, "", url);
    renderList();
    loadThread(true);
  });

  searchInput?.addEventListener("input", renderList);

  const threadHost = threadEl.closest(".live-chat-main") || mainEl;
  cvrChatBindMessageActions(threadEl, {
    host: threadHost,
    getMessage: (id) => state.messages.find((x) => x.id === id),
    canUnsend: (m) => m.senderRole === "staff" && (!state.userId || Number(m.senderId) === Number(state.userId)),
    onDelete: async (m, scope) => {
      const { body } = await cvrApiPost("chat/delete-message.php", { messageId: m.id, scope });
      if (!body?.success) {
        if (typeof cvrToast === "function") cvrToast(body?.message || "Could not remove that message.", "danger");
        return;
      }
      if (scope === "me") state.messages = state.messages.filter((x) => x.id !== m.id);
      else cvrChatApplyDeleted(state.messages, [m.id]);
      renderThread();
      loadInbox();
    },
  });

  deleteBtn?.addEventListener("click", async () => {
    const conv = state.conversations.find((c) => Number(c.id) === Number(state.activeId));
    if (!conv || !mainEl) return;
    const scope = await cvrChatChoose(mainEl, {
      title: `Delete chat with ${conv.customerName || "this guest"}?`,
      options: [
        { value: "me", label: "Delete for me", desc: "Hides it from your inbox only. Other staff and the guest still have it. It returns if the guest sends a new message." },
        { value: "everyone", label: "Delete for everyone", desc: "Permanently deletes the conversation, photos and files for the guest and all staff. This can't be undone.", danger: true },
      ],
    });
    if (!scope) return;
    const { body } = await cvrApiPost("chat/delete.php", { conversationId: conv.id, scope });
    if (!body?.success) {
      if (typeof cvrToast === "function") cvrToast(body?.message || "Could not delete the conversation.", "danger");
      return;
    }
    state.conversations = state.conversations.filter((c) => Number(c.id) !== Number(conv.id));
    state.activeId = 0;
    state.messages = [];
    state.lastId = 0;
    if (typeof window.cvrChatClearPending === "function") window.cvrChatClearPending();
    const url = new URL(location.href);
    url.searchParams.delete("c");
    history.replaceState({}, "", url);
    renderList();
    renderThread();
    if (typeof cvrToast === "function") cvrToast(scope === "everyone" ? "Conversation deleted for everyone." : "Conversation deleted for you.", "success");
    loadInbox();
  });

  const sendReply = async ({ text, imageUrl, fileUrl, fileName }) => {
    if (!state.activeId) return false;
    const payload = { conversationId: state.activeId };
    if (text) payload.message = text;
    if (imageUrl) payload.imageUrl = imageUrl;
    if (fileUrl) { payload.fileUrl = fileUrl; payload.fileName = fileName || ""; }
    const sendBtn = document.getElementById("liveChatReplySend");
    if (sendBtn) sendBtn.disabled = true;
    const { body } = await cvrApiPost("chat/send.php", payload);
    if (sendBtn) sendBtn.disabled = false;
    if (!body?.success) {
      if (typeof cvrToast === "function") cvrToast(body?.message || "Could not send reply.", "danger");
      return false;
    }
    if (body.data.message) {
      state.messages.push(body.data.message);
      state.lastId = body.data.message.id;
    }
    renderThread();
    loadInbox();
    return true;
  };

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (form.dataset.cvrPending) return; // a file is waiting for confirmation
    const text = (input?.value || "").trim();
    if (!text || !state.activeId) return;
    const ok = await sendReply({ text });
    if (ok && input) input.value = "";
  });

  if (typeof cvrChatMountTools === "function") {
    cvrChatMountTools({ form, input, sendImage: (a) => sendReply(typeof a === "string" ? { imageUrl: a } : a) });
  }

  input?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form?.requestSubmit();
    }
  });

  const poll = async () => {
    await loadInbox();
    if (state.activeId) await loadThread(false);
  };

  poll();
  setInterval(() => {
    if (!document.hidden) poll();
  }, 2500);
}

window.initAdminLiveChatPage = initAdminLiveChatPage;
