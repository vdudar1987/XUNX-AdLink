const dashboardApi = (path, options = {}) =>
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

const formatCurrency = (value) =>
  new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: "RUB",
    maximumFractionDigits: 2,
  }).format(value || 0);

const fillText = (selector, value) => {
  const el = document.querySelector(selector);
  if (el) el.textContent = value;
};

const renderRows = (selector, rows, columns) => {
  const tbody = document.querySelector(selector);
  if (!tbody) return;
  tbody.innerHTML = "";
  rows.forEach((row) => {
    const tr = document.createElement("tr");
    columns.forEach((key) => {
      const td = document.createElement("td");
      td.textContent = row[key];
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
};

const initAdvertiser = async () => {
  const overview = await dashboardApi("/api/advertiser.php?action=overview");
  fillText("[data-metric=balance]", formatCurrency(overview.balance));
  fillText("[data-metric=impressions]", overview.impressions);
  fillText("[data-metric=clicks]", overview.clicks);
  fillText("[data-metric=ctr]", `${overview.ctr}%`);
  fillText("[data-metric=spent]", formatCurrency(overview.spent));
  fillText("[data-metric=cpc]", formatCurrency(overview.cpc));

  const campaignStats = await dashboardApi(
    "/api/advertiser.php?action=stats_campaigns"
  );
  renderRows(
    "[data-table=campaign-stats]",
    campaignStats.stats,
    ["name", "impressions", "clicks", "ctr", "spent", "cpc"]
  );

  const siteStats = await dashboardApi("/api/advertiser.php?action=stats_sites");
  renderRows("[data-table=site-stats]", siteStats.stats, [
    "site",
    "impressions",
    "clicks",
    "ctr",
  ]);

  const pageStats = await dashboardApi("/api/advertiser.php?action=stats_pages");
  renderRows("[data-table=page-stats]", pageStats.stats, [
    "page_url",
    "impressions",
    "clicks",
    "ctr",
  ]);

  const keywordStats = await dashboardApi(
    "/api/advertiser.php?action=stats_keywords"
  );
  renderRows("[data-table=keyword-stats]", keywordStats.stats, [
    "keyword",
    "impressions",
    "clicks",
    "ctr",
  ]);

  const campaigns = await dashboardApi("/api/advertiser.php?action=campaigns");
  renderRows("[data-table=campaign-list]", campaigns.campaigns, [
    "name",
    "title",
    "ad_type",
    "cpc",
    "active",
  ]);
};

const initPublisher = async () => {
  const overview = await dashboardApi("/api/publisher.php?action=overview");
  fillText("[data-metric=balance]", formatCurrency(overview.balance));
  fillText("[data-metric=sites]", overview.sites);

  const sites = await dashboardApi("/api/publisher.php?action=sites");
  renderRows("[data-table=site-list]", sites.sites, [
    "site_domain",
    "category",
    "region_code",
    "moderation_status",
    "verification_status",
  ]);

  const codeBox = document.querySelector("[data-js-code]");
  if (codeBox && sites.sites.length > 0) {
    const siteId = sites.sites[0].id;
    codeBox.textContent = `<script src="${window.location.origin}/ad.js" data-endpoint="${window.location.origin}/api/ads.php" data-click-endpoint="${window.location.origin}/api/click.php" data-impression-endpoint="${window.location.origin}/api/impression.php" data-publisher-id="${siteId}"></script>`;
  }
};

const initTransactions = async () => {
  const data = await dashboardApi("/api/transactions.php");
  renderRows("[data-table=transactions]", data.transactions, [
    "created_at",
    "type",
    "description",
    "amount",
  ]);
};

const initNotifications = async () => {
  const data = await dashboardApi("/api/notifications.php");
  const list = document.querySelector("[data-notifications]");
  if (!list) return;
  list.innerHTML = "";
  if (data.notifications.length === 0) {
    list.innerHTML = "<p class='muted'>Уведомлений пока нет.</p>";
    return;
  }
  data.notifications.forEach((note) => {
    const item = document.createElement("div");
    item.className = "card";
    item.textContent = note.message;
    list.appendChild(item);
  });
};

const initProfile = () => {
  const forms = document.querySelectorAll("[data-profile-form]");
  forms.forEach((form) => {
    const notice = form.querySelector("[data-profile-notice]");
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(form).entries());
      dashboardApi("/api/profile.php", {
        method: "POST",
        body: JSON.stringify(payload),
      })
        .then(() => {
          notice.textContent = "Профиль обновлен.";
          notice.classList.add("notice");
        })
        .catch((error) => {
          notice.textContent = error.message;
          notice.classList.add("notice", "notice--warn");
        });
    });
  });
};

const initForms = () => {
  const campaignForm = document.querySelector("[data-campaign-form]");
  if (campaignForm) {
    const notice = campaignForm.querySelector("[data-campaign-notice]");
    campaignForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(campaignForm).entries());
      payload.keywords = (payload.keywords || "")
        .split(/[\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean);
      dashboardApi("/api/advertiser.php?action=create_campaign", {
        method: "POST",
        body: JSON.stringify(payload),
      })
        .then(() => {
          notice.textContent = "Кампания создана.";
          notice.classList.add("notice");
          campaignForm.reset();
        })
        .catch((error) => {
          notice.textContent = error.message;
          notice.classList.add("notice", "notice--warn");
        });
    });
  }

  const siteForm = document.querySelector("[data-site-form]");
  if (siteForm) {
    const notice = siteForm.querySelector("[data-site-notice]");
    siteForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(siteForm).entries());
      dashboardApi("/api/publisher.php?action=create_site", {
        method: "POST",
        body: JSON.stringify(payload),
      })
        .then(() => {
          notice.textContent = "Сайт добавлен.";
          notice.classList.add("notice");
          siteForm.reset();
        })
        .catch((error) => {
          notice.textContent = error.message;
          notice.classList.add("notice", "notice--warn");
        });
    });
  }

  const payoutForm = document.querySelector("[data-payout-form]");
  if (payoutForm) {
    const notice = payoutForm.querySelector("[data-payout-notice]");
    payoutForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(payoutForm).entries());
      dashboardApi("/api/publisher.php?action=payout", {
        method: "POST",
        body: JSON.stringify(payload),
      })
        .then(() => {
          notice.textContent = "Запрос на вывод отправлен.";
          notice.classList.add("notice");
          payoutForm.reset();
        })
        .catch((error) => {
          notice.textContent = error.message;
          notice.classList.add("notice", "notice--warn");
        });
    });
  }
};

const initLogout = () => {
  const btn = document.querySelector("[data-logout]");
  if (!btn) return;
  btn.addEventListener("click", () => {
    dashboardApi("/api/auth_logout.php", { method: "POST" }).finally(() => {
      window.location.href = "/auth.html";
    });
  });
};

document.addEventListener("DOMContentLoaded", async () => {
  const response = await dashboardApi("/api/auth_me.php");
  if (!response.authenticated) {
    window.location.href = "/auth.html";
    return;
  }

  fillText("[data-user-name]", response.user.name);
  fillText("[data-user-role]", response.user.role);
  document.querySelectorAll("[data-profile-form]").forEach((form) => {
    const nameInput = form.querySelector("input[name=name]");
    const emailInput = form.querySelector("input[name=email]");
    if (nameInput) nameInput.value = response.user.name;
    if (emailInput) emailInput.value = response.user.email;
  });

  document
    .querySelectorAll("[data-role-section]")
    .forEach((section) => {
      section.hidden = section.dataset.roleSection !== response.user.role;
    });

  if (response.user.role === "advertiser") {
    await initAdvertiser();
  }

  if (response.user.role === "publisher") {
    await initPublisher();
  }

  await initTransactions();
  await initNotifications();
  initProfile();
  initForms();
  initLogout();
});
