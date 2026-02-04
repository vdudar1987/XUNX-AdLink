const api = (path, options = {}) =>
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

const setNotice = (el, message, warn = false) => {
  if (!el) return;
  el.textContent = message;
  el.classList.toggle("notice--warn", warn);
  el.classList.add("notice");
};

const initRegister = () => {
  const form = document.querySelector("[data-register-form]");
  if (!form) return;
  const notice = document.querySelector("[data-register-notice]");
  api("/api/meta.php")
    .then((data) => {
      const roleSelect = form.querySelector("select[name=role]");
      data.roles.forEach((role) => {
        const option = document.createElement("option");
        option.value = role.value;
        option.textContent = role.label;
        roleSelect.appendChild(option);
      });
    })
    .catch(() => {});

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(form).entries());
    api("/api/auth_register.php", {
      method: "POST",
      body: JSON.stringify(payload),
    })
      .then(() => {
        setNotice(notice, "Регистрация успешна! Подтвердите email.");
        form.reset();
      })
      .catch((error) => setNotice(notice, error.message, true));
  });
};

const initLogin = () => {
  const form = document.querySelector("[data-login-form]");
  if (!form) return;
  const notice = document.querySelector("[data-login-notice]");
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(form).entries());
    api("/api/auth_login.php", {
      method: "POST",
      body: JSON.stringify(payload),
    })
      .then((data) => {
        const role = data.user?.role;
        if (role === "admin") {
          window.location.href = "/admin.html";
          return;
        }
        window.location.href = "/dashboard.html";
      })
      .catch((error) => setNotice(notice, error.message, true));
  });
};

const initVerify = () => {
  const form = document.querySelector("[data-verify-form]");
  if (!form) return;
  const notice = document.querySelector("[data-verify-notice]");
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(form).entries());
    api("/api/auth_verify.php", {
      method: "POST",
      body: JSON.stringify(payload),
    })
      .then(() => {
        setNotice(notice, "Email подтвержден. Теперь можно войти.");
        form.reset();
      })
      .catch((error) => setNotice(notice, error.message, true));
  });
};

const initReset = () => {
  const form = document.querySelector("[data-reset-form]");
  if (!form) return;
  const notice = document.querySelector("[data-reset-notice]");
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(form).entries());
    api("/api/auth_reset.php", {
      method: "POST",
      body: JSON.stringify(payload),
    })
      .then(() => {
        setNotice(notice, "Пароль обновлен. Теперь можно войти.");
        form.reset();
      })
      .catch((error) => setNotice(notice, error.message, true));
  });
};

document.addEventListener("DOMContentLoaded", () => {
  initRegister();
  initLogin();
  initVerify();
  initReset();
});
