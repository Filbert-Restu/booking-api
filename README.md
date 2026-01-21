# 🚀 Booking API - Workflow Management System

Sistem manajemen dokumen dengan workflow approval otomatis untuk organisasi kampus (HIMA, UKM, Prodi, Fakultas).

## ⚡ Quick Start

```bash
# 1. Jalankan migrasi database
php artisan migrate:fresh

# 2. Seed data awal (roles, units, users, workflows)
php artisan db:seed --class=WorkflowSeeder

# 3. Jalankan server
php artisan serve
```

## 📧 Demo Accounts

| Email                       | Password | Role        | Unit                |
| --------------------------- | -------- | ----------- | ------------------- |
| dekan@ft.ac.id              | password | Dekan       | Fakultas            |
| kaprodi.if@ft.ac.id         | password | Kaprodi     | Prodi Informatika   |
| ketua.hima.if@student.ac.id | password | Ketua HIMA  | HIMA Informatika    |
| ketua.hima.te@student.ac.id | password | Ketua HIMA  | HIMA Teknik Elektro |
| ketua.hima.ts@student.ac.id | password | Ketua HIMA  | HIMA Teknik Sipil   |
| ketua.bem@student.ac.id     | password | Ketua BEM   | BEM Fakultas        |
| senat@ft.ac.id              | password | Ketua Senat | Senat Fakultas      |
| ketua.ukm@student.ac.id     | password | Ketua UKM   | UKM Olahraga        |

## 📚 Documentation

- **[Implementation Guide](WORKFLOW_IMPLEMENTATION_GUIDE.md)** - Panduan lengkap API & penggunaan
- **[Technical Diagrams](WORKFLOW_DIAGRAMS.md)** - Diagram database & flow
- **[Organizational Structure](ORGANIZATIONAL_STRUCTURE.md)** - Struktur organisasi lengkap (HIMA, BEM, Senat, UKM)
- **[Organizational Structure](ORGANIZATIONAL_STRUCTURE.md)** - Struktur organisasi lengkap (HIMA, BEM, Senat, UKM)

## 🔑 Key Features

✅ Dynamic workflow routing (SELF, PARENT, FACULTY_LEADER, SPECIFIC_CATEGORY)  
✅ Multi-level approval system  
✅ Complete audit trail & document logs  
✅ Hierarchical organization structure  
✅ Role-based access control

## 📡 Main API Endpoints

```
GET    /api/workflows                      # List workflows
GET    /api/documents                      # My documents & pending approvals
POST   /api/documents                      # Create document
POST   /api/documents/{id}/submit          # Submit document
POST   /api/documents/{id}/approve         # Approve & forward
POST   /api/documents/{id}/reject          # Reject document
POST   /api/documents/{id}/revise          # Return for revision
```

## 🏗️ Tech Stack

- Laravel 11
- PHP 8.2+
- MySQL/PostgreSQL/SQLite
- Laravel Sanctum (Authentication)

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
