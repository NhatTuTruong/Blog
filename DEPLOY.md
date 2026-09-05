# Hướng dẫn deploy Blog lên server

Tài liệu này mô tả **từng bước deploy** project Laravel 10 + Filament lên VPS/shared hosting, các **yêu cầu server**, extension PHP cần bật, và cấu hình **cron** cho auto-blog / social queue.

---

## 1. Server cần có gì?

### 1.1. Tối thiểu (chạy website + admin)

| Thành phần | Yêu cầu |
|------------|---------|
| **OS** | Linux (Ubuntu 22.04/24.04 khuyến nghị) hoặc Windows Server + IIS/Apache |
| **PHP** | **8.1 trở lên** (khuyến nghị **8.2** hoặc **8.3**) |
| **Web server** | **Nginx** (khuyến nghị) hoặc Apache 2.4 |
| **Database** | **MySQL 8** / MariaDB 10.6+ |
| **Composer** | 2.x (cài trên server hoặc build vendor trên máy local rồi upload) |
| **SSL** | Let's Encrypt (Certbot) — bắt buộc cho production |
| **RAM** | Tối thiểu **2 GB** (khuyến nghị **4 GB** nếu bật auto-blog AI + Apify) |
| **Disk** | Tối thiểu **20 GB** (ảnh/video blog + storage tăng nhanh) |

> **Không cần Node.js / npm** — project không dùng Vite build frontend.

### 1.2. PHP extensions bắt buộc

Bật các extension sau trong `php.ini` (Linux: thường qua `apt install php8.2-*`):

```
pdo_mysql
mbstring
openssl
tokenizer
xml
ctype
json
bcmath
fileinfo
curl
gd          ← xử lý ảnh, convert WebP
zip         ← import/export Excel
intl        ← khuyến nghị
```

Kiểm tra:

```bash
php -m
php -v
```

### 1.3. PHP extensions tùy chọn (theo tính năng)

| Extension / Tool | Dùng cho |
|------------------|----------|
| **imap** | Đồng bộ email inbox (`imap:sync-inbox`) |
| **FFmpeg** (`bin/ffmpeg` hoặc cài system-wide) | Video social media (Instagram/Facebook/Pinterest) |
| **Redis + php-redis** | Cache/session nhanh hơn (khuyến nghị khi traffic cao) |

### 1.4. Giới hạn PHP (quan trọng)

Project upload ảnh/video lớn trong admin. Cấu hình tối thiểu:

```ini
upload_max_filesize = 128M
post_max_size = 128M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

Auto-blog (Gemini + Apify) có thể chạy lâu — cron command set `set_time_limit(900)`; đảm bảo `max_execution_time` không quá thấp (≥ 300).

### 1.5. Cron — bắt buộc cho production

Scheduler Laravel chạy **mỗi phút**. Không cấu hình cron → **auto-blog, đăng social, email định kỳ sẽ không hoạt động**.

Các job trong `app/Console/Kernel.php`:

- `imap:sync-inbox` — mỗi phút (nếu bật IMAP)
- `blogs:process-queue` — auto-blog queue
- `social:auto-queue` — social auto queue
- `instagram:process-queue` / `facebook:process-queue` / `pinterest:process-queue`
- `email:process-recurring`
- `blogs:generate-daily` — mỗi giờ (trong khung giờ cấu hình admin)

---

## 2. Chuẩn bị trước khi deploy

### 2.1. Trên máy local

```bash
# Chạy test cơ bản
composer install --no-dev --optimize-autoloader
php artisan test   # nếu có test

# Đảm bảo không commit file nhạy cảm
# .env, storage/logs, node_modules (nếu có) — KHÔNG upload .env từ máy dev lên prod
```

### 2.2. Trên server — tạo database

```sql
CREATE DATABASE blog_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'blog_user'@'localhost' IDENTIFIED BY 'MAT_KHAU_MANH';
GRANT ALL PRIVILEGES ON blog_db.* TO 'blog_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2.3. Tạo user Linux cho app (VPS)

```bash
sudo adduser --disabled-password --gecos "" blogapp
sudo mkdir -p /var/www/blog
sudo chown -R blogapp:www-data /var/www/blog
```

---

## 3. Deploy từng bước

### Bước 1 — Upload code

**Cách A: Git (khuyến nghị)**

```bash
cd /var/www/blog
git clone <URL_REPO> .
# hoặc git pull nếu đã clone trước đó
```

**Cách B: ZIP/FTP**

- Upload toàn bộ project **trừ** `vendor/`, `node_modules/`, `.env`
- Document root của web server phải trỏ vào thư mục **`public/`**, không phải root project

### Bước 2 — Cài dependencies

```bash
cd /var/www/blog
composer install --no-dev --optimize-autoloader
```

Nếu server không có Composer:

```bash
# Trên máy local
composer install --no-dev --optimize-autoloader
# Upload cả thư mục vendor/ lên server
```

### Bước 3 — Tạo file `.env`

```bash
cp .env.example .env   # nếu có file mẫu
# hoặc tạo .env mới
nano .env
php artisan key:generate
```

### Bước 4 — Cấu hình `.env` production

```env
APP_NAME="Tên website"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=blog_db
DB_USERNAME=blog_user
DB_PASSWORD=MAT_KHAU_MANH

# Cache — file (mặc định) hoặc redis nếu đã cài
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Mail (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Google Analytics (tùy chọn — load sau cookie consent)
GOOGLE_ANALYTICS_ID=G-XXXXXXXX

# AI Auto Blog
GEMINI_API_KEY=your_gemini_key
GEMINI_MODEL=gemini-2.5-flash-lite
GEMINI_TIMEOUT_SECONDS=120

# Apify (ảnh auto-blog)
APIFY_API_TOKEN=your_apify_token

# Coupon sync API (nếu dùng)
COUPON_SYNC_ENABLED=true
COUPON_SYNC_API_TOKEN=token_bao_mat_dai

# Social queue
SOCIAL_MEDIA_QUEUE_STALE_MINUTES=10
SOCIAL_VIDEO_UI_ENABLED=true

# IMAP (nếu đồng bộ email)
IMAP_HOST=imap.gmail.com
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_USERNAME=
IMAP_PASSWORD=
IMAP_AUTO_SYNC_SECONDS=120

# FFmpeg (nếu xử lý video social — đường dẫn tuyệt đối)
# FFMPEG_BINARY=/var/www/blog/bin/ffmpeg/ffmpeg
```

> **`APP_URL` phải đúng domain HTTPS** — ảnh hưởng sitemap, canonical, OG image, link storage.

### Bước 5 — Migration & seed (lần đầu)

```bash
php artisan migrate --force
# Tùy chọn seed danh mục mặc định:
# php artisan db:seed --class=BlogCategorySeeder
```

**Lưu ý:** Đảm bảo đã chạy đủ migration, đặc biệt:

- `add_priority_to_blogs_table`
- `add_processing_started_at_to_social_queue_items`
- `add_blog_category_pivot_and_multi_auto_blog`

Kiểm tra:

```bash
php artisan migrate:status
```

### Bước 6 — Storage link & quyền thư mục

```bash
php artisan storage:link
```

Quyền ghi (Linux):

```bash
sudo chown -R blogapp:www-data /var/www/blog
sudo chmod -R 775 storage bootstrap/cache public/storage
sudo chmod -R 775 public/storage
```

Filament upload ảnh vào `public/storage/` — thư mục này **phải ghi được**.

### Bước 7 — Tối ưu Laravel (production)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache   # Laravel 10+
```

Sau mỗi lần đổi `.env` hoặc config:

```bash
php artisan config:clear
php artisan config:cache
```

### Bước 8 — Tạo tài khoản admin

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@yourdomain.com',
    'password' => bcrypt('MatKhauManh123!'),
    'is_admin' => true,
]);
```

Hoặc dùng seeder nếu project có sẵn.

Đăng nhập admin: **`https://yourdomain.com/admin`**

---

## 4. Cấu hình Web Server

### 4.1. Nginx (khuyến nghị)

File `/etc/nginx/sites-available/blog`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/blog/public;

    index index.php;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    client_max_body_size 128M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|webp|svg|ico|css|js|woff2|mp3|mp4)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

Kích hoạt:

```bash
sudo ln -s /etc/nginx/sites-available/blog /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 4.2. Apache

DocumentRoot = `/var/www/blog/public`

Bật module:

```bash
sudo a2enmod rewrite ssl headers
```

Project đã có `public/.htaccess` với rewrite + giới hạn upload.

### 4.3. SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

---

## 5. Cron Scheduler — bắt buộc

Mở crontab user chạy web app:

```bash
crontab -e
```

Thêm **đúng 1 dòng**:

```cron
* * * * * cd /var/www/blog && php artisan schedule:run >> /dev/null 2>&1
```

Kiểm tra scheduler:

```bash
cd /var/www/blog
php artisan schedule:list
php artisan schedule:run -v
```

---

## 6. FFmpeg (tùy chọn — video social)

Nếu dùng đăng video Instagram/Facebook/Pinterest:

**Cách 1:** Upload sẵn binary (không cần cài trên server)

```bash
# Trên máy local (Windows/Linux)
php artisan social-media:install-encoder
# Upload thư mục bin/ffmpeg/ lên server
php artisan social-media:check-encoder
```

**Cách 2:** Cài system-wide

```bash
sudo apt install ffmpeg
# Thêm vào .env:
# FFMPEG_BINARY=/usr/bin/ffmpeg
```

---

## 7. Kiểm tra sau deploy

### 7.1. Health check

```bash
curl https://yourdomain.com/health
```

Kết quả mong đợi: `{"app":"ok","db":"ok",...}`

### 7.2. SEO

- `https://yourdomain.com/robots.txt`
- `https://yourdomain.com/sitemap.xml`
- Trang chủ + 1 bài blog: xem source có `<link rel="canonical">`, meta description

### 7.3. Admin

- `https://yourdomain.com/admin/login`
- Upload ảnh bài viết → kiểm tra hiển thị trên frontend
- Chạy thử 1 item auto-blog queue (nếu có API key)

### 7.4. Performance tools

- [PageSpeed Insights](https://pagespeed.web.dev/)
- [Google Search Console](https://search.google.com/search-console) — submit sitemap

---

## 8. OPcache & PHP-FPM (khuyến nghị VPS)

`/etc/php/8.2/fpm/conf.d/99-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
```

Sau deploy code mới:

```bash
sudo systemctl reload php8.2-fpm
```

> Khi `validate_timestamps=0`, cần reload PHP-FPM sau mỗi lần deploy.

---

## 9. Redis (tùy chọn — traffic cao)

```bash
sudo apt install redis-server php8.2-redis
```

`.env`:

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

```bash
php artisan config:cache
```

---

## 10. Checklist bảo mật production

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] HTTPS bắt buộc (redirect 301)
- [ ] `.env` không public (nằm ngoài document root — Laravel mặc định OK)
- [ ] Đổi mật khẩu admin mạnh
- [ ] `COUPON_SYNC_API_TOKEN` đủ dài, không commit vào git
- [ ] Chặn truy cập thư mục nhạy cảm (Nginx deny `/.env`)
- [ ] Backup DB định kỳ (cron mysqldump)
- [ ] Giới hạn IP admin (tùy chọn — firewall / Nginx allowlist)

---

## 11. Cập nhật phiên bản mới (re-deploy)

```bash
cd /var/www/blog
git pull origin main

composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl reload php8.2-fpm   # nếu OPcache validate_timestamps=0
```

---

## 12. Xử lý sự cố thường gặp

| Triệu chứng | Nguyên nhân | Cách xử lý |
|-------------|-------------|------------|
| 500 Internal Server Error | Quyền `storage/`, thiếu `.env`, chưa `key:generate` | `storage/logs/laravel.log`, `chmod 775 storage bootstrap/cache` |
| Ảnh upload không hiện | Chưa `storage:link` | `php artisan storage:link` |
| Auto-blog không chạy | Thiếu cron | Thêm cron `schedule:run` |
| Auto-blog lỗi AI | Thiếu/sai `GEMINI_API_KEY` | Cấu hình admin hoặc `.env` |
| Không có ảnh inline blog | Thiếu Apify token | `APIFY_API_TOKEN` |
| Email sync lỗi | Thiếu ext **imap** | `sudo apt install php8.2-imap` + restart PHP-FPM |
| Video social lỗi | Thiếu FFmpeg | Upload `bin/ffmpeg` hoặc cài ffmpeg |
| CSS/ config cũ sau deploy | Cache | `php artisan view:clear && php artisan config:cache` |
| Sitemap/OG sai URL | `APP_URL` sai | Sửa `.env` → `config:cache` |

---

## 13. Cấu trúc thư mục quan trọng

```
/var/www/blog/
├── app/                 # Code ứng dụng
├── bootstrap/cache/     # Phải ghi được (config/route cache)
├── config/              # Cấu hình
├── database/migrations/ # Migration DB
├── public/              # Document root (web server trỏ vào đây)
│   ├── index.php
│   ├── storage/         # Ảnh public (symlink hoặc upload trực tiếp)
│   └── .htaccess
├── resources/views/     # Blade templates
├── routes/web.php       # Route frontend
├── storage/             # Logs, cache, file tạm — phải ghi được
├── vendor/              # Composer packages
└── .env                 # Cấu hình môi trường (KHÔNG commit)
```

---

## 14. Liên hệ tính năng ↔ cấu hình

| Tính năng | Cần bật / cấu hình |
|-----------|-------------------|
| Website + blog | PHP, MySQL, Nginx, SSL |
| Admin Filament | User `is_admin`, `/admin` |
| Auto-blog AI | Cron + `GEMINI_API_KEY` + (tuỳ chọn) `APIFY_API_TOKEN` |
| Multi-category | Migration pivot `blog_blog_category` |
| Instagram/Facebook/Pinterest queue | Cron + API credentials trong admin |
| Video social | FFmpeg + `SOCIAL_VIDEO_PREPARE_ENABLED` |
| Email template / gửi mail | SMTP trong `.env` |
| IMAP inbox | ext `imap` + IMAP env |
| Coupon sync API | `POST /api/coupons/sync` + `COUPON_SYNC_API_TOKEN` |
| SEO sitemap | Tự động tại `/sitemap.xml` (cache 1h) |

---

*Tài liệu cập nhật theo codebase Laravel 10 + Filament 3. Nếu deploy shared hosting (cPanel), document root vẫn phải là `public/` và cần thêm cron tương đương trong cPanel → Cron Jobs.*
