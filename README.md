# Video Downloader (PHP)

Веб-сервис для скачивания видео по ссылке без API.

Поддержка (через `yt-dlp`):
- OK (`ok.ru`)
- VK (`vk.com`)
- RuTube (`rutube.ru`)
- Дзен (`dzen.ru`, опционально)
- Яндекс.Видео (`yandex.*`, опционально)

## Что изменено

- Интегрирована поддержка обёртки **YtDlp-PHP** (если установлена через Composer).
- Добавлен приоритетный режим: для **MP4** сервис сначала пытается выдать **прямую ссылку** на медиа без хранения файла на сервере.
- Добавлен fallback на локальное временное хранение (когда прямую ссылку получить нельзя или для `mp3`).
- Добавлена очистка временных файлов:
  - автоматическая очистка при каждом API-запросе (TTL 1 час),
  - отдельный CLI-скрипт для cron: `scripts/cleanup_temp_videos.php`.

## Стек
- **Backend:** PHP 8+ (рекомендуется)
- **Frontend:** HTML + CSS + JS (современный UI)
- **Downloader engine:** `yt-dlp` / `YtDlp-PHP`
- **Post-processing:** `ffmpeg` / (опционально) `PHP-FFMpeg`

## Быстрый запуск

1. Установите зависимости на сервере:
   - `yt-dlp`
   - `ffmpeg`
2. (Опционально) Установите PHP-обёртки:

```bash
composer require norkunas/youtube-dl-php php-ffmpeg/php-ffmpeg
```

3. Направьте web-root на папку `public/`.
4. Откройте сайт в браузере.

Локально:

```bash
php -S 0.0.0.0:8080 -t public
```

## Как это работает

- Пользователь вставляет ссылку и выбирает формат (`mp4` или `mp3`).
- Фронт отправляет POST в `public/api.php`.
- Бэкенд:
  1. Валидирует URL и домен.
  2. Для MP4 пытается получить прямой URL через `YtDlp-PHP` (если есть) или CLI `yt-dlp --get-url`.
  3. Если прямая ссылка недоступна — скачивает во временное хранилище `public/storage/`.
  4. Возвращает URL для скачивания.

## Очистка временных файлов

Ручной запуск:

```bash
php scripts/cleanup_temp_videos.php
```

С TTL (секунды):

```bash
php scripts/cleanup_temp_videos.php 1800
```

Пример cron (каждые 15 минут):

```bash
*/15 * * * * /usr/bin/php /path/to/project/scripts/cleanup_temp_videos.php 3600
```

## Файлы
- `public/index.php` — UI страницы.
- `public/assets/style.css` — стили.
- `public/assets/app.js` — фронтенд-логика формы.
- `public/api.php` — backend API + отдача файлов.
- `scripts/cleanup_temp_videos.php` — CLI-очистка временных файлов.
- `public/storage/` — временное хранилище скачанных файлов.

## Важно

- Некоторые источники могут требовать обновлённый `yt-dlp`.
- Прямые ссылки могут быть временными и истекать.
- Ответственность за соблюдение авторских прав несёт владелец сервиса.
