# Configuration - Samapiece

## 1. Configuration de base

### Variables d'environnement

Créez un fichier `.env` à la racine du projet:

```env
# Mode d'exécution
FLASK_ENV=development
FLASK_DEBUG=True

# Clé secrète (générez une nouvelle clé)
SECRET_KEY=your-secret-key-here-change-in-production

# Configuration SMTP pour les emails
SMTP_SERVER=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=votre_email@gmail.com
SMTP_PASSWORD=votre_mot_de_passe_application

# Email d'expédition
EMAIL_FROM=votre_email@gmail.com
EMAIL_SUBJECT_PREFIX=[Samapiece]

# Configuration de la base de données
DATABASE_URL=sqlite:///samapiece.db

# Configuration des uploads
MAX_UPLOAD_SIZE_MB=16
ALLOWED_IMAGE_FORMATS=jpg,jpeg,png,gif

# Configuration de sécurité
SESSION_COOKIE_SECURE=True
SESSION_COOKIE_HTTPONLY=True
SESSION_COOKIE_SAMESITE=Lax
REMEMBER_COOKIE_DURATION=2592000
```

## 2. Configuration Flask

### app.py - Configuration primaire

```python
app.config.update(
    # Sécurité
    SECRET_KEY='votre_cle_secrete',
    SESSION_COOKIE_SECURE=True,
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE='Lax',
    PERMANENT_SESSION_LIFETIME=timedelta(days=30),
    
    # Upload
    MAX_CONTENT_LENGTH=16 * 1024 * 1024,  # 16 MB
    UPLOAD_FOLDER='uploads',
    
    # SMTP
    MAIL_SERVER='smtp.gmail.com',
    MAIL_PORT=587,
    MAIL_USE_TLS=True,
    MAIL_USERNAME='votre_email@gmail.com',
    MAIL_PASSWORD='mot_de_passe_app',
)
```

## 3. Configuration de la base de données JSON

### Structure de data.json

```json
{
  "users": [
    {
      "id": "uuid",
      "email": "user@example.com",
      "mot_passe_hash": "hash_du_mot_de_passe",
      "nom": "Dupont",
      "prenom": "Jean",
      "telephone": "+33123456789",
      "date_creation": "2025-12-11T10:00:00",
      "actif": true
    }
  ],
  "documents": [
    {
      "id": "uuid",
      "type_piece": "Passeport",
      "nom": "Martin",
      "prenom": "Sophie",
      "date_naissance": "1990-05-15",
      "lieu_naissance": "Paris",
      "numero_piece": "AB123456",
      "date_declaration": "2025-12-11T10:00:00",
      "photo": "filename.jpg",
      "utilisateur_id": "uuid_du_declarant",
      "code_recuperation": "ABCD-EFGH-IJKL",
      "statut": "disponible"
    }
  ],
  "alertes": [
    {
      "id": "uuid",
      "type_piece": "Permis",
      "nom": "Bernard",
      "prenom": "Pierre",
      "date_naissance": "1985-03-20",
      "lieu_naissance": "Lyon",
      "email": "pierre@example.com",
      "date_creation": "2025-12-11T10:00:00",
      "active": true
    }
  ],
  "administrateurs": [
    {
      "id": "uuid",
      "email": "admin@samapiece.com",
      "mot_passe_hash": "hash_du_mot_de_passe",
      "nom": "Administrateur",
      "date_creation": "2025-12-11T10:00:00",
      "actif": true
    }
  ],
  "suivi_connexions": {},
  "journaux_securite": []
}
```

## 4. Configuration de sécurité

### Mots de passe

Les mots de passe doivent satisfaire:

- **Longueur minimum:** 8 caractères
- **Majuscules:** Au moins 1
- **Chiffres:** Au moins 1
- **Caractères spéciaux:** Au moins 1 parmi !@#$%^&*()-_=+[]{}|;:,.<>?

### Exemple de mot de passe valide
```
MonMotDePasse123!
Mon@dP2024!
Admin#2025Secure
```

### Limitations de connexion

- **Tentatives autorisées:** 5
- **Blocage après:** 5 tentatives échouées
- **Durée du blocage:** 30 minutes
- **Enregistrement:** Journalisé avec timestamp et IP

## 5. Configuration des emails

### Gmail - Configuration SMTP

1. **Activer l'authentification deux facteurs**
2. **Générer un mot de passe d'application:**
   - Aller à myaccount.google.com/apppasswords
   - Sélectionner "Mail" et "Windows"
   - Copier le mot de passe généré

3. **Configuration:**
```env
SMTP_SERVER=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=votre.email@gmail.com
SMTP_PASSWORD=mot_de_passe_application_16_caracteres
EMAIL_FROM=votre.email@gmail.com
```

### Autres fournisseurs SMTP

#### Outlook
```env
SMTP_SERVER=smtp-mail.outlook.com
SMTP_PORT=587
SMTP_USERNAME=votre.email@outlook.com
SMTP_PASSWORD=votre_mot_de_passe
```

#### OVH
```env
SMTP_SERVER=ssl0.ovh.net
SMTP_PORT=465
SMTP_USERNAME=email@votredomaine.com
SMTP_PASSWORD=votre_mot_de_passe
```

## 6. Configuration des uploads

### Dossier uploads

```bash
# Créer le dossier
mkdir uploads

# Permissions (Linux/Mac)
chmod 755 uploads

# Permissions recommandées
# Propriétaire: lire, écrire, exécuter
# Groupe: lire, exécuter
# Autres: lire, exécuter
```

### Types de fichiers autorisés

```python
EXTENSIONS_AUTORISEES = {'jpg', 'jpeg', 'png', 'gif'}
TAILLE_MAX_MO = 16
```

### Optimisation des images

Les images sont automatiquement:
- Redimensionnées si > 2000x2000px
- Converties en JPEG
- Compressées (qualité: 85%)

## 7. Configuration du serveur web

### Développement (Flask intégré)

```bash
FLASK_ENV=development
FLASK_DEBUG=True
python app.py
```

### Production avec Gunicorn

```bash
# Installation
pip install gunicorn

# Lancer avec 4 workers
gunicorn -w 4 -b 0.0.0.0:5000 --timeout 120 app:app

# Avec fichier de configuration
gunicorn -c gunicorn_config.py app:app
```

### Configuration Gunicorn (gunicorn_config.py)

```python
import multiprocessing

bind = "0.0.0.0:5000"
workers = multiprocessing.cpu_count() * 2 + 1
worker_class = "sync"
worker_connections = 1000
timeout = 120
keepalive = 5
max_requests = 1000
max_requests_jitter = 50
access_log_format = '%(h)s %(l)s %(u)s %(t)s "%(r)s" %(s)s %(b)s "%(f)s" "%(a)s"'
```

## 8. Configuration Nginx

### Fichier de configuration

```nginx
upstream samapiece {
    server 127.0.0.1:5000;
}

server {
    listen 80;
    listen [::]:80;
    server_name votre_domaine.com www.votre_domaine.com;

    # Redirection HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name votre_domaine.com www.votre_domaine.com;

    # Certificats SSL
    ssl_certificate /chemin/vers/certificat.pem;
    ssl_certificate_key /chemin/vers/cle_privee.pem;

    # Paramètres SSL
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;
    gzip_min_length 1000;

    # Limite de taille d'upload
    client_max_body_size 20M;

    # Headers de sécurité
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Proxy vers Gunicorn
    location / {
        proxy_pass http://samapiece;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_buffering off;
        proxy_request_buffering off;
    }

    # Fichiers statiques
    location /static {
        alias /chemin/vers/samapiece/static;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location /uploads {
        alias /chemin/vers/samapiece/uploads;
        expires 7d;
    }
}
```

## 9. Sauvegarde et restauration

### Script de sauvegarde automatique

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/home/user/backups/samapiece"
DATE=$(date +%Y%m%d_%H%M%S)
SOURCE="/home/user/samapiece"

mkdir -p "$BACKUP_DIR"

# Sauvegarder data.json
cp "$SOURCE/data.json" "$BACKUP_DIR/data_$DATE.json"

# Sauvegarder uploads
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" "$SOURCE/uploads/"

# Garder seulement les 30 dernières sauvegardes
find "$BACKUP_DIR" -type f -mtime +30 -delete

echo "Sauvegarde completee: $DATE"
```

### Restaurer une sauvegarde

```bash
# Restaurer data.json
cp backups/data_20251211_100000.json data.json

# Restaurer uploads
tar -xzf backups/uploads_20251211_100000.tar.gz
```

## 10. Dépannage

### Problème: UnicodeEncodeError sur Windows

**Solution:**
```bash
set PYTHONIOENCODING=utf-8
python app.py
```

### Problème: Impossible de créer des fichiers dans uploads

**Solution:**
```bash
# Vérifier les permissions
ls -la uploads/

# Changer les permissions
chmod 755 uploads/

# Vérifier l'espace disque
df -h
```

### Problème: Les emails ne sont pas envoyés

**Solution:**
```
1. Vérifier la configuration SMTP
2. Vérifier que FLASK_ENV ne est pas 'testing'
3. Vérifier les logs: journaux_securite dans data.json
4. Tester la connexion SMTP:
   python -c "import smtplib; smtplib.SMTP('smtp.gmail.com', 587).starttls()"
```

---

**Dernière mise à jour:** 11 décembre 2025  
**Version:** 1.0.0
