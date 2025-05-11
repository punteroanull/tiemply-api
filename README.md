# Tiemply API

Tiemply is a comprehensive employee time management system built with Laravel 12. It provides a robust REST API that enables companies to manage employee attendance, vacation requests, absences, and more, all in one place.

## 🌟 Features

- **Employee time tracking** - Clock in/out functionality with detailed reports
- **Absence management** - Track vacations, sick leaves, and other absence types
- **Request workflow** - Allow employees to request time off with approval flows
- **Multi-company support** - Manage multiple companies with separate configurations
- **Role-based access control** - Granular permissions for administrators, managers, and employees
- **Detailed reporting** - Daily, weekly, and monthly reports for attendance and absences


## 🛣️ Roadmap

### ✅ Completed (Current Version)
- REST API architecture with Laravel 12
- Database structure with UUIDs as primary keys
- Authentication system using Laravel Sanctum
- User management with role-based permissions
- Company and employee management
- Work log tracking (clock in/out)
- Absence types configuration
- Absence request workflow
- Docker containerization
- Comprehensive test suite

### 🚧 In Progress (Coming Soon)
- Enhanced reporting capabilities

### 📅 Planned (Future Releases)
- Internationalization support
- Calendar integration (Google Calendar, Outlook)
- Push notifications
- Payroll export calculations based on worked hours
- Export monthly data in JSON/PDF
- Advanced analytics dashboard
- Geolocation validation for clock in/out
- Integration with popular HR systems
- Digital signature for important documents
- AI-powered insights for attendance patterns

The API is currently functional for core time-tracking operations, allowing companies to register, add employees, and track attendance while we continue to expand its capabilities.

## 🚀 Getting Started

### Prerequisites

- Docker and Docker Compose
- Git
- Composer (if not using Docker)
- PHP 8.3+ (if not using Docker)
- Node.js and NPM (if not using Docker)

### Installation

#### Using Docker (Recommended)

1. Clone the repository
   ```bash
   git clone https://github.com/punteroanull/tiemply-api.git
   cd tiemply-api
   ```

2. Copy the example environment file
   ```bash
   cp .env.example .env
   ```

3. Configure your environment variables in the `.env` file

4. Start the Docker containers
   ```bash
   docker-compose up -d
   ```

5. The API will be available at `http://localhost:8000`

#### Manual Installation

1. Clone the repository
   ```bash
   git clone https://github.com/punteroanull/tiemply-api.git
   cd tiemply-api
   ```

2. Install PHP dependencies
   ```bash
   composer install
   ```

3. Copy the example environment file
   ```bash
   cp .env.example .env
   ```

4. Configure your environment variables in the `.env` file

5. Generate an application key
   ```bash
   php artisan key:generate
   ```

6. Run the database migrations and seed initial data
   ```bash
   php artisan migrate --seed
   ```

7. Start the development server
   ```bash
   php artisan serve
   ```

8. The API will be available at `http://localhost:8000`

### Usage

#### Authentication

The API uses Laravel Sanctum for authentication. To get started:

1. Register a user:
   ```bash
   curl -X POST http://localhost:8000/api/register \
     -H "Content-Type: application/json" \
     -d '{"name":"Test User","email":"test@example.com","password":"password","password_confirmation":"password"}'
   ```

2. Login to get an API token:
   ```bash
   curl -X POST http://localhost:8000/api/login \
     -H "Content-Type: application/json" \
     -d '{"email":"test@example.com","password":"password"}'
   ```

3. Use the token in subsequent requests:
   ```bash
   curl -X GET http://localhost:8000/api/me \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
   ```

#### Admin Panel

The application includes a Filament admin panel accessible at:

```
http://localhost:8000/admin
```

Default credentials for the demo admin:
- Email: `admin@tiemply.com`
- Password: `password`

#### API Documentation

The API documentation is available at:

```
http://localhost:8000/api/documentation
```

For a complete list of API endpoints and their usage, refer to the API documentation.

## 🛠️ Technologies Used

- **[Laravel 12](https://laravel.com/)** - The PHP framework for web artisans
- **[MariaDB](https://mariadb.org/)** - Advanced MySQL database server
- **[Docker](https://www.docker.com/)** - Container platform
- **[Laravel Sanctum](https://laravel.com/docs/sanctum)** - Authentication system
- **[Filament](https://filamentphp.com/)** - Admin panel framework
- **[UUIDs](https://en.wikipedia.org/wiki/Universally_unique_identifier)** - Used for primary keys

## 📄 License

This project is licensed under the Apache License 2.0 - see the LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request