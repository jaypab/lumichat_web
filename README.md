# LumiCHAT

LumiCHAT is a Laravel + Rasa–powered mental-health chatbot.

---

## ✨ Features
- ⚡ Real-time chat interface with **Rasa** integration
- 💾 Chat history saved to the database
- 🧩 Responsive UI built with **Tailwind CSS**
- 🔒 Secure and private messaging

---

## 🧰 Prerequisites
- 🐘 **PHP** 8.2+ and **Composer** 2.x  
- 🟩 **Node.js** 18/20 LTS and **npm**  
- 🐬 **MySQL** 8+ (or MariaDB 10.5+)  
- 🐍 **Python** 3.10 (recommended for Rasa 3.x) and **pip**  
- 🔧 **Git**

> We **do not commit** `.venv/`, `rasa-bot/models/`, `*.tar.gz`, `/vendor`, `/node_modules`, or `.env`.  
> Each developer creates these locally.

---

## 🧬 Clone the project
```bash
git clone https://github.com/jaypab/lumichat_web.git
cd Lumichat_v1.7
cd lumichat-backend

PHP dependencies
composer install

# 2) Environment
cp .env.example .env
php artisan key:generate

# 3) Configure database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=lumichat
# DB_USERNAME=root
# DB_PASSWORD=secret

# 4) Migrate (add --seed if seeds are available)
php artisan migrate

# 5) Frontend assets (Vite + Tailwind)
npm install
npm run dev   # use `npm run build` for production

# 6) (Linux/macOS) ensure writable folders
# sudo chmod -R 775 storage bootstrap/cache

php artisan serve
# ➜ http://127.0.0.1:8000

Rasa-Bot Installation
cd lumichat-backend/rasa-bot

# 1) Create a Python virtual environment (do NOT commit .venv/)
python -m venv .venv

# 2) Activate it
# ▸ Windows:
.venv\Scripts\activate
# ▸ macOS/Linux:
# source .venv/bin/activate

# 3) Install Python dependencies
# If requirements.txt is missing, create it on a working machine via:  pip freeze > requirements.txt
pip install -r requirements.txt

# 4) Train NLU/Core
rasa train

Run Rasa (2 terminals)
# Terminal A – Rasa server (REST API)
rasa run --enable-api -p 5005

# Terminal B – Actions server (if you have custom actions)
rasa run actions -p 5055

🔗 Connect Laravel ↔ Rasa
Add to lumichat-backend/.env:
# Rasa HTTP API
RASA_BASE_URL=http://127.0.0.1:5005
RASA_REST_WEBHOOK=/webhooks/rest/webhook

# (Optional) custom actions server webhook
RASA_ACTION_SERVER=http://127.0.0.1:5055/webhook

# Timeouts (seconds)
RASA_TIMEOUT=20

🚀Start everything (TL;DR)
# Laravel
cd lumichat-backend
php artisan serve

#Vite(React)
cd lumichat-backend
npm run dev

#Rasa
cd lumichat-backend/rasa-bot
.venv\Scripts\activate     # or: source .venv/bin/activate
rasa run --enable-api -p 5005
# (optional) if using custom actions:
# rasa run actions -p 5055

🛠️ Troubleshooting
- DB errors → check .env DB_* values, ensure MySQL is running, then php artisan migrate.
- Rasa not responding → confirm rasa run …5005 (and rasa run actions …5055 if needed) are running.
- Permissions (Linux/macOS) → chmod -R 775 storage bootstrap/cache.
- Yellow “M” in VS Code but git status is clean → Developer: Reload Window.
- Large files rejected on push → don’t commit .venv/ or rasa-bot/models/ (already ignored).

