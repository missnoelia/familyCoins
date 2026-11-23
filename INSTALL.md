# Instalación

Guía para instalar y ejecutar CoupleCoins en local.

## Requisitos

- [Git](https://git-scm.com/)
- [Node.js](https://nodejs.org/) (versión X o superior)
- [PostgreSQL](https://www.postgresql.org/) (versión X o superior)

## 1. Clonar el repositorio

git clone https://github.com/missnoelia/familyCoins.git
cd familyCoins
git checkout develop

## 2. Crear la base de datos

1. Crea la base de datos:

   createdb familycoins

2. Ejecuta el script con las tablas:

   psql -d familycoins -f database.sql

## 3. Configurar las variables de entorno

Copia el archivo de ejemplo y rellena tus datos:

cp backend/.env.example backend/.env

Variables necesarias en `backend/.env`:

| Variable | Descripción | Ejemplo |
|---|---|---|
| DB_HOST | Servidor de la base de datos | localhost |
| DB_PORT | Puerto de PostgreSQL | 5432 |
| DB_NAME | Nombre de la base de datos | familycoins |
| DB_USER | Usuario de PostgreSQL | postgres |
| DB_PASSWORD | Contraseña de PostgreSQL | tu_contraseña |
| JWT_SECRET | Clave secreta para el login | una_clave_larga |
| PORT | Puerto del backend | 3000 |

## 4. Instalar dependencias y arrancar

### Backend

cd backend
npm install
npm start

### Frontend

cd frontend
npm install
npm start

## 5. Probar que funciona

- Backend: http://localhost:3000
- Frontend: http://localhost:5173

## Problemas frecuentes

- **No conecta con la base de datos:** revisa que PostgreSQL esté arrancado y que los datos de `.env` sean correctos.
- **Puerto ocupado:** cambia el valor de `PORT` en `.env`.
- **Faltan dependencias:** ejecuta `npm install` de nuevo en la carpeta que falle.