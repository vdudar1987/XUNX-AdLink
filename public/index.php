<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Video Downloader</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
  <main class="app">
    <section class="card">
      <h1>Скачать видео по ссылке</h1>
      <p class="subtitle">OK, VK, RuTube, Дзен и Яндекс.Видео (без API)</p>

      <form id="downloadForm" class="form">
        <label for="url">Ссылка на видео</label>
        <input id="url" name="url" type="url" placeholder="https://..." required />

        <label for="format">Формат</label>
        <select id="format" name="format">
          <option value="mp4">Видео (MP4)</option>
          <option value="mp3">Аудио (MP3)</option>
        </select>

        <button type="submit">Скачать</button>
      </form>

      <div id="status" class="status hidden"></div>
      <a id="downloadLink" class="download-link hidden" href="#">Скачать файл</a>

      <div class="note">
        <strong>Важно:</strong> сервер должен иметь <code>yt-dlp</code> и <code>ffmpeg</code>.
      </div>
    </section>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
