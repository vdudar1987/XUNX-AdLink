const form = document.getElementById('downloadForm');
const statusBox = document.getElementById('status');
const downloadLink = document.getElementById('downloadLink');

const showStatus = (message, type = 'success') => {
  statusBox.classList.remove('hidden', 'error', 'success');
  statusBox.classList.add(type);
  statusBox.textContent = message;
};

const hideStatus = () => {
  statusBox.classList.add('hidden');
};

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  hideStatus();
  downloadLink.classList.add('hidden');

  const button = form.querySelector('button');
  button.disabled = true;
  showStatus('Обработка ссылки... Пытаемся выдать прямую ссылку без сохранения на сервере.', 'success');

  try {
    const payload = {
      url: form.url.value.trim(),
      format: form.format.value,
      direct: true,
    };

    const response = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.error || 'Ошибка сервера');
    }

    const isDirect = data.mode === 'direct';
    showStatus(data.message || (isDirect
      ? 'Готово! Выдана прямая ссылка для скачивания.'
      : 'Готово! Файл подготовлен к скачиванию.'), 'success');

    downloadLink.href = data.download_url;
    downloadLink.target = '_blank';
    downloadLink.rel = 'noopener noreferrer';
    downloadLink.textContent = isDirect
      ? 'Открыть прямую ссылку на файл'
      : `Скачать: ${data.filename}`;
    downloadLink.classList.remove('hidden');
  } catch (error) {
    showStatus(error.message || 'Не удалось скачать видео.', 'error');
  } finally {
    button.disabled = false;
  }
});
