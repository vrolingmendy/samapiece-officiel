# Guide de Migration vers PostgreSQL

## Prérequis

1. **Installer PostgreSQL**:
   - Windows: Télécharger depuis https://www.postgresql.org/download/windows/
   - Linux: `sudo apt-get install postgresql postgresql-contrib`
   - macOS: `brew install postgresql`

2. **Démarrer le service PostgreSQL** (si pas automatique)

## Étapes de Migration

### 1. Créer la base de données PostgreSQL

```bash
# Ouvrir le terminal PostgreSQL
psql -U postgres

# Dans psql, créer la base de données et l'utilisateur
CREATE USER samapiece WITH PASSWORD 'samapiece_password';
CREATE DATABASE samapiece OWNER samapiece;
ALTER USER samapiece CREATEDB;

# Sortir de psql
\q
```

### 2. Configurer les variables d'environnement

Mettre à jour le fichier `.env`:
```
DB_HOST=localhost
DB_USER=samapiece
DB_PASS=samapiece_password
DB_NAME=samapiece
DB_PORT=5432
```

### 3. Migrer les données

```bash
# Naviguer au répertoire samapiece
cd C:\Users\v.mendes\Desktop\Guisnako\samapiece

# Activer l'environnement virtuel
.\.venv\Scripts\Activate.ps1

# Exécuter la migration
python migrate_to_postgres.py
```

### 4. Remplacer app.py par app_postgres.py

```bash
# Sauvegarder l'ancienne version
Rename-Item app.py app_json.py

# Utiliser la version PostgreSQL
Rename-Item app_postgres.py app.py
```

### 5. Lancer l'application

```bash
$env:SMTP_SERVER='mail.empire-18.com'
$env:SMTP_PORT='465'
$env:SMTP_USERNAME='mail@empire-18.com'
$env:SMTP_PASSWORD='Passer123@!'
$env:EMAIL_FROM='mail@empire-18.com'
$env:DB_HOST='localhost'
$env:DB_USER='samapiece'
$env:DB_PASS='samapiece_password'
$env:DB_NAME='samapiece'

python app.py
```

L'application est maintenant avec PostgreSQL comme base de données "NoSQL" (avec colonnes JSON flexibles)!
