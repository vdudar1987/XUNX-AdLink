const adminApi = (path, options = {}) =>
  fetch(path, {
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    ...options,
  }).then(async (res) => {
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const error = data.error || "Ошибка запроса";
      throw new Error(error);
    }
    return data;
  });

const fillText = (selector, value) => {
  const el = document.querySelector(selector);
  if (el) el.textContent = value;
};

const setNotice = (el, message, warn = false) => {
  if (!el) return;
  el.textContent = message;
  el.classList.toggle("notice--warn", warn);
  el.classList.add("notice");
};

const createSelect = (options, value) => {
  const select = document.createElement("select");
  options.forEach((option) => {
    const item = document.createElement("option");
    item.value = option;
    item.textContent = option;
    if (option === value) {
      item.selected = true;
    }
    select.appendChild(item);
  });
  return select;
};

const createRowAction = (label) => {
  const button = document.createElement("button");
  button.type = "button";
  button.className = "button button--ghost";
  button.textContent = label;
  return button;
};

const initAuth = async () => {
  const data = await adminApi("/api/auth_me.php");
  if (!data.authenticated || data.user.role !== "admin") {
    window.location.href = "/auth.html";
    return null;
  }
  return data.user;
};

const loadOverview = async () => {
  const data = await adminApi("/api/admin.php?action=overview");
  Object.entries(data.counts).forEach(([key, value]) => {
    fillText(`[data-admin-metric="${key}"]`, value);
  });
};

const loadUsers = async () => {
  const data = await adminApi("/api/admin.php?action=users");
  const tbody = document.querySelector('[data-admin-table="users"]');
  if (!tbody) return;
  tbody.innerHTML = "";
  data.users.forEach((user) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${user.id}</td>
      <td>${user.name}</td>
      <td>${user.email}</td>
    `;
    const roleSelect = createSelect(
      ["advertiser", "publisher", "admin"],
      user.role
    );
    const statusSelect = createSelect(
      ["active", "pending", "blocked"],
      user.status
    );
    const action = createRowAction("Сохранить");
    action.addEventListener("click", async () => {
      await adminApi("/api/admin.php?action=update_user", {
        method: "POST",
        body: JSON.stringify({
          id: user.id,
          role: roleSelect.value,
          status: statusSelect.value,
        }),
      });
      await loadOverview();
    });
    const tdRole = document.createElement("td");
    tdRole.appendChild(roleSelect);
    const tdStatus = document.createElement("td");
    tdStatus.appendChild(statusSelect);
    const tdAction = document.createElement("td");
    tdAction.appendChild(action);
    tr.appendChild(tdRole);
    tr.appendChild(tdStatus);
    tr.appendChild(tdAction);
    tbody.appendChild(tr);
  });
};

const loadPublishers = async () => {
  const data = await adminApi("/api/admin.php?action=publishers");
  const tbody = document.querySelector('[data-admin-table="publishers"]');
  if (!tbody) return;
  tbody.innerHTML = "";
  data.sites.forEach((site) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${site.id}</td>
      <td>${site.site_domain || site.site_url}</td>
      <td>${site.category || "-"}</td>
      <td>${site.region_code || "-"}</td>
    `;
    const moderation = createSelect(
      ["pending", "approved", "rejected"],
      site.moderation_status
    );
    const verification = createSelect(
      ["unverified", "verified"],
      site.verification_status
    );
    const active = document.createElement("input");
    active.type = "checkbox";
    active.checked = Boolean(site.active);
    const action = createRowAction("Сохранить");
    action.addEventListener("click", async () => {
      await adminApi("/api/admin.php?action=update_publisher", {
        method: "POST",
        body: JSON.stringify({
          id: site.id,
          moderation_status: moderation.value,
          verification_status: verification.value,
          active: active.checked,
        }),
      });
      await loadOverview();
    });
    const tdModeration = document.createElement("td");
    tdModeration.appendChild(moderation);
    const tdVerification = document.createElement("td");
    tdVerification.appendChild(verification);
    const tdActive = document.createElement("td");
    tdActive.appendChild(active);
    const tdAction = document.createElement("td");
    tdAction.appendChild(action);
    tr.appendChild(tdModeration);
    tr.appendChild(tdVerification);
    tr.appendChild(tdActive);
    tr.appendChild(tdAction);
    tbody.appendChild(tr);
  });
};

const loadCampaigns = async () => {
  const data = await adminApi("/api/admin.php?action=campaigns");
  const tbody = document.querySelector('[data-admin-table="campaigns"]');
  if (!tbody) return;
  tbody.innerHTML = "";
  data.campaigns.forEach((campaign) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${campaign.id}</td>
      <td>${campaign.name}</td>
      <td>${campaign.advertiser_name}</td>
      <td>${campaign.cpc}</td>
    `;
    const moderation = createSelect(
      ["pending", "approved", "rejected"],
      campaign.moderation_status
    );
    const active = document.createElement("input");
    active.type = "checkbox";
    active.checked = Boolean(campaign.active);
    const action = createRowAction("Сохранить");
    action.addEventListener("click", async () => {
      await adminApi("/api/admin.php?action=update_campaign", {
        method: "POST",
        body: JSON.stringify({
          id: campaign.id,
          moderation_status: moderation.value,
          active: active.checked,
        }),
      });
      await loadOverview();
    });
    const tdModeration = document.createElement("td");
    tdModeration.appendChild(moderation);
    const tdActive = document.createElement("td");
    tdActive.appendChild(active);
    const tdAction = document.createElement("td");
    tdAction.appendChild(action);
    tr.appendChild(tdModeration);
    tr.appendChild(tdActive);
    tr.appendChild(tdAction);
    tbody.appendChild(tr);
  });
};

const loadPayouts = async () => {
  const data = await adminApi("/api/admin.php?action=payouts");
  const tbody = document.querySelector('[data-admin-table="payouts"]');
  if (!tbody) return;
  tbody.innerHTML = "";
  data.payouts.forEach((payout) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${payout.id}</td>
      <td>${payout.publisher_name}</td>
      <td>${payout.site_domain}</td>
      <td>${payout.amount}</td>
      <td>${payout.method}</td>
    `;
    const approve = createRowAction("Одобрить");
    const reject = createRowAction("Отклонить");
    approve.addEventListener("click", async () => {
      await adminApi("/api/admin.php?action=update_payout", {
        method: "POST",
        body: JSON.stringify({ id: payout.id, status: "approved" }),
      });
      await loadOverview();
      await loadPayouts();
    });
    reject.addEventListener("click", async () => {
      await adminApi("/api/admin.php?action=update_payout", {
        method: "POST",
        body: JSON.stringify({ id: payout.id, status: "rejected" }),
      });
      await loadOverview();
      await loadPayouts();
    });
    const tdAction = document.createElement("td");
    tdAction.appendChild(approve);
    tdAction.appendChild(reject);
    tdAction.style.display = "flex";
    tdAction.style.gap = "8px";
    tr.appendChild(tdAction);
    tbody.appendChild(tr);
  });
};

const loadSettings = async () => {
  const data = await adminApi("/api/admin.php?action=settings");
  const settings = data.settings;
  Object.entries(settings.fraud).forEach(([key, value]) => {
    const input = document.querySelector(
      `[name="fraud.${key}"]`
    );
    if (input) input.value = value;
  });
  Object.entries(settings.finance).forEach(([key, value]) => {
    const input = document.querySelector(
      `[name="finance.${key}"]`
    );
    if (input) input.value = value;
  });
};

const initSettingsForms = () => {
  const fraudForm = document.querySelector('[data-admin-form="fraud"]');
  const financeForm = document.querySelector('[data-admin-form="finance"]');
  const fraudNotice = document.querySelector('[data-admin-notice="fraud"]');
  const financeNotice = document.querySelector(
    '[data-admin-notice="finance"]'
  );

  if (fraudForm) {
    fraudForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(fraudForm).entries());
      await adminApi("/api/admin.php?action=update_settings", {
        method: "POST",
        body: JSON.stringify({ settings: payload }),
      });
      setNotice(fraudNotice, "Настройки антифрода сохранены.");
    });
  }

  if (financeForm) {
    financeForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(financeForm).entries());
      await adminApi("/api/admin.php?action=update_settings", {
        method: "POST",
        body: JSON.stringify({ settings: payload }),
      });
      setNotice(financeNotice, "Финансовые настройки сохранены.");
    });
  }
};

const initLogout = () => {
  const button = document.querySelector("[data-logout]");
  if (!button) return;
  button.addEventListener("click", async () => {
    await adminApi("/api/auth_logout.php", { method: "POST" });
    window.location.href = "/auth.html";
  });
};

document.addEventListener("DOMContentLoaded", async () => {
  const user = await initAuth();
  if (!user) return;
  initLogout();
  initSettingsForms();
  await Promise.all([
    loadOverview(),
    loadUsers(),
    loadPublishers(),
    loadCampaigns(),
    loadPayouts(),
    loadSettings(),
  ]);
});
