<div align="center">

# 🎯 Prospec

### Lead prospecting panel for a web design agency

![PHP](https://img.shields.io/badge/PHP_8-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
![No Framework](https://img.shields.io/badge/framework-none-blue?style=flat-square)
![Status](https://img.shields.io/badge/status-in_use-success?style=flat-square)

</div>

---

## 🎯 The Problem

Selling websites to small local businesses starts with finding the right ones: companies that are active and well reviewed, but have no website, only a social media profile, or a site that is broken on mobile. Doing this by hand means searching Google Maps city by city, checking each site, keeping a spreadsheet and making sure two salespeople never contact the same business.

## ✅ The Solution

A team panel that turns a search like *"veterinary clinic in Ribeirão Preto"* into a scored, deduplicated lead list and a ready-to-send WhatsApp queue.

| Feature | Description |
|---|---|
| 🔎 **Search by niche and city** | Google Maps results via the Serper API, with autocomplete for all ~5,570 Brazilian municipalities (IBGE official list) |
| 🧮 **Automatic qualification** | Drops businesses with too few reviews and classifies the rest as *no website*, *social media only* or *weak website* |
| 🩺 **Website audit** | Checks a prospect's site for issues such as missing mobile viewport and turns each finding into a sales argument |
| 📊 **Lead funnel** | Status pipeline (new, contacted, replied, meeting, closed, lost) with a full action history per lead |
| 💬 **Outreach queue** | Personalized WhatsApp message per lead, opened with one click |
| 👥 **Multi-user** | Each lead has a single owner (atomic claim), with a daily search quota per user to control API cost |
| 📈 **Dashboard** | Reply and meeting rates by niche and lead profile; CSV export for admins |

> **Note:** the interface is in Portuguese, as the tool is used to prospect Brazilian businesses.

## 🔒 Security

- Passwords stored as bcrypt hashes, with a forced password change on first login
- CSRF tokens on every form, strict Content Security Policy with per-request nonce
- Login throttling by IP and username, timing-safe comparison for unknown users
- Prepared statements for all queries; `.htaccess` blocks direct access to config, library and data files

## 🛠️ Tech Stack

PHP 8 (no framework), MySQL/MariaDB, vanilla HTML, CSS and JavaScript. External APIs: [Serper](https://serper.dev) (Google Maps search) and IBGE (municipality list).

## 🚀 Setup

1. Upload the files to the web root (e.g. `public_html/`).
2. Create a MySQL database and import `schema.sql`.
3. Copy `config.example.php` to `config.php` and fill in the database credentials, the Serper API key and the users.
4. Generate each user's initial password hash:
   ```bash
   php -r "echo password_hash('INITIAL_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
   ```
5. Open the site and log in. The system asks for a new password on first access.

## 💡 What I Learned

The hard part was not the search but the team workflow: two people must never message the same business, quotas must hold under concurrent requests, and a slow website audit must not break the database connection on shared hosting. Each of those became a small, explicit design decision (atomic lead claim, upsert counters, reconnect after long checks) instead of an assumption.

---

<div align="center">

Built by [Giuseppe Maset Neto](https://www.linkedin.com/in/giuseppemasetneto) for [NEMAX Digital](https://nemaxdigital.com.br)

</div>
