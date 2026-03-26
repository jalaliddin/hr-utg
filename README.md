# Urganchtransgaz HR Tizimi

Laravel 13 + Vue 3 (Vuetify 3) asosida qurilgan HR va tabel boshqaruv tizimi.

---

## Texnologiyalar

| Qatlam | Stack |
|--------|-------|
| Backend | Laravel 13, PHP 8.3, MySQL 8 |
| Frontend | Vue 3, Vuetify 3, Pinia, TypeScript |
| Auth | Laravel Sanctum (session-based) |
| PDF | DomPDF |
| Qurilmalar | Hikvision ISAPI |

---

## VPS ga Deploy qilish

### Talablar

- Docker Engine 24+
- Docker Compose v2+
- VPS: minimal 1 CPU, 1GB RAM, 10GB disk

### 1. Kodni VPS ga yuklash

```bash
git clone <repo_url> /opt/hr
cd /opt/hr
```

### 2. Backend `.env` faylini tayyorlash

```bash
cp backend/.env.production.example backend/.env
```

`.env` ni tahrirlang:

```bash
nano backend/.env
```

Muhim qiymatlar:
- `APP_KEY` — `php artisan key:generate --show` bilan olish
- `APP_URL` — serveringizning IP yoki domen
- `DB_PASSWORD` — xavfsiz parol
- `DB_ROOT_PASSWORD` — MySQL root paroli (docker-compose.yml bilan bir xil bo'lishi shart)

> **Eslatma:** `DB_HOST=mysql` bo'lishi kerak (container nomi).

### 3. docker-compose `.env` (ixtiyoriy)

Root papkada `.env` yaratib port o'zgartirish mumkin:

```bash
echo "APP_PORT=8080" > .env
echo "DB_ROOT_PASSWORD=yourStrongPassword" >> .env
```

### 4. Ishga tushirish

```bash
docker compose up -d --build
```

Birinchi marta image build bo'ladi (~3-5 daqiqa). Keyin konteynerlar ishga tushadi, migration avtomatik bajariladi.

### 5. Tekshirish

```bash
# Konteynerlar holati
docker compose ps

# Backend loglar
docker compose logs app

# Foydalanuvchi yaratish
docker compose exec app php artisan tinker --execute="
  App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password123'),
    'role' => 'super_admin',
  ]);
"
```

Brauzerda `http://YOUR_SERVER_IP` ni oching.

---

## Yangilash

```bash
git pull
docker compose up -d --build
```

Migration avtomatik bajariladi (entrypoint da).

---

## Foydali buyruqlar

```bash
# Barcha loglarni ko'rish
docker compose logs -f

# Bitta servis logini ko'rish
docker compose logs -f app
docker compose logs -f worker

# Artisan buyruq ishlatish
docker compose exec app php artisan <command>

# MySQL ga kirish
docker compose exec mysql mysql -u root -p hr_prod

# Queue to'xtatish/qayta ishga tushirish
docker compose restart worker

# Servislarni to'xtatish
docker compose down

# Ma'lumotlar bilan birga to'liq o'chirish (ehtiyot bo'ling!)
docker compose down -v
```

---

## HTTPS sozlash (Nginx Proxy Manager yoki Certbot)

### Variant A: Nginx Proxy Manager (tavsiya)

1. `docker-compose.yml` da portni o'zgartiring: `APP_PORT=8080`
2. Nginx Proxy Manager orqali domain + SSL qo'shing, upstream `http://localhost:8080` ga yo'naltiring

### Variant B: Certbot (to'g'ridan-to'g'ri)

```bash
apt install certbot python3-certbot-nginx
# docker-compose.yml da portni 80 qoldirib, nginx ni to'xtatib:
docker compose stop nginx
certbot --standalone -d yourdomain.com
```

Keyin `docker/nginx/default.conf` da SSL qo'shing va `nginx` ga `443:443` port qo'shing.

---

## Arxitektura

```
Internet
    │
    ▼
nginx (port 80)
    ├── /api/*      → FastCGI → app (php-fpm:9000)
    ├── /sanctum/*  → FastCGI → app (php-fpm:9000)
    ├── /storage/*  → to'g'ridan-to'g'ri fayl (shared volume)
    └── /*          → Vue SPA (index.html)

app (php-fpm)
    └── MySQL (mysql:3306)

worker
    └── queue:work (database driver)

scheduler
    └── schedule:run (har daqiqa)
```

---

## Loyiha tuzilmasi

```
hr2.urtg.uz/
├── backend/          # Laravel 13
│   ├── app/
│   ├── database/
│   ├── routes/
│   ├── Dockerfile
│   └── docker-entrypoint.sh
├── frontend/         # Vue 3 + Vuetify 3
│   ├── src/
│   └── Dockerfile
├── docker/
│   └── nginx/
│       └── default.conf
├── docker-compose.yml
└── README.md
```
