# -*- coding: utf-8 -*-
"""
Samapiece - Application Flask pour la récupération de documents officiels perdus
Une plateforme permettant de déclarer des documents trouvés et de rechercher des documents perdus.
"""

import os
import json
import uuid
import datetime
from werkzeug.utils import secure_filename
from werkzeug.security import generate_password_hash, check_password_hash
from flask import Flask, render_template, request, redirect, url_for, session, flash, send_from_directory, jsonify
import smtplib
import ssl
from email.mime.text import MIMEText
from itsdangerous import URLSafeTimedSerializer, SignatureExpired, BadSignature
from bson.objectid import ObjectId
from PIL import Image
import io
from functools import wraps
from datetime import timedelta

# ============================================================
# Gestionnaire de Sécurité
# ============================================================
import hashlib
import hmac

class GestionnaireSécurité:
    """Gestionnaire de sécurité pour protection contre les attaques"""
    
    # Configuration de sécurité
    MAX_TENTATIVES_CONNEXION = 5  # Nombre maximum de tentatives de connexion
    TEMPS_BLOCAGE_MINUTES = 30     # Temps de blocage du compte en minutes
    
    @staticmethod
    def obtenir_hash_client(request):
        """Génère un hash unique pour le client basé sur l'IP"""
        ip = request.remote_addr or request.headers.get('X-Forwarded-For', 'unknown')
        return hashlib.sha256(ip.encode()).hexdigest()[:16]
    
    @staticmethod
    def nettoyer_saisie(saisie_utilisateur):
        """Supprime les caractères dangereux et désinfecte l'entrée"""
        if not isinstance(saisie_utilisateur, str):
            return str(saisie_utilisateur)
        
        # Supprimer les caractères de contrôle dangereux
        caracteres_dangereux = ['<', '>', '"', "'", '&', '%', '\\', '/']
        nettoyee = saisie_utilisateur
        for char in caracteres_dangereux:
            if char in nettoyee and not (char == '&' and '&' in saisie_utilisateur.split()):
                nettoyee = nettoyee.replace(char, '')
        
        return nettoyee.strip()
    
    @staticmethod
    def valider_format_email(email):
        """Valide le format de l'email"""
        import re
        pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
        return re.match(pattern, email) is not None
    
    @staticmethod
    def valider_force_mot_passe(mot_passe):
        """Valide la force du mot de passe"""
        if len(mot_passe) < 8:
            return False, "Mot de passe trop court (minimum 8 caracteres)"
        if not any(c.isupper() for c in mot_passe):
            return False, "Doit contenir au moins une majuscule"
        if not any(c.isdigit() for c in mot_passe):
            return False, "Doit contenir au moins un chiffre"
        if not any(c in '!@#$%^&*()-_=+[]{}|;:,.<>?' for c in mot_passe):
            return False, "Doit contenir au moins un caractere special"
        return True, "Mot de passe valide"
    
    @staticmethod
    def verifier_tentatives_connexion(identifiant, objet_requete):
        """Vérifie et enregistre les tentatives de connexion échouées"""
        db = charger_db()
        hash_client = GestionnaireSécurité.obtenir_hash_client(objet_requete)
        
        # Créer une clé de connexion pour le suivi
        cle_connexion = f"tentatives_connexion_{identifiant}_{hash_client}"
        
        # Initialiser le suivi s'il n'existe pas
        if 'suivi_connexions' not in db:
            db['suivi_connexions'] = {}
        
        if cle_connexion not in db['suivi_connexions']:
            db['suivi_connexions'][cle_connexion] = {
                'tentatives': 0,
                'premiere_tentative': datetime.datetime.now().isoformat(),
                'bloquee_jusqu_a': None
            }
        
        suivi = db['suivi_connexions'][cle_connexion]
        
        # Vérifier si le compte est bloqué
        if suivi['bloquee_jusqu_a']:
            bloquee_jusqu_a = datetime.datetime.fromisoformat(suivi['bloquee_jusqu_a'])
            if datetime.datetime.now() < bloquee_jusqu_a:
                return False, 'COMPTE_BLOQUE'
            else:
                # Débloquer après le temps écoulé
                suivi['tentatives'] = 0
                suivi['bloquee_jusqu_a'] = None
                sauvegarder_db(db)
        
        return True, suivi['tentatives']
    
    @staticmethod
    def enregistrer_tentative_echouee(identifiant, objet_requete):
        """Enregistre une tentative de connexion échouée"""
        db = charger_db()
        hash_client = GestionnaireSécurité.obtenir_hash_client(objet_requete)
        cle_connexion = f"tentatives_connexion_{identifiant}_{hash_client}"
        
        if 'suivi_connexions' not in db:
            db['suivi_connexions'] = {}
        
        if cle_connexion not in db['suivi_connexions']:
            db['suivi_connexions'][cle_connexion] = {
                'tentatives': 0,
                'premiere_tentative': datetime.datetime.now().isoformat(),
                'bloquee_jusqu_a': None
            }
        
        suivi = db['suivi_connexions'][cle_connexion]
        suivi['tentatives'] += 1
        
        # Bloquer le compte après 5 tentatives
        if suivi['tentatives'] >= GestionnaireSécurité.MAX_TENTATIVES_CONNEXION:
            blocage_jusqu_a = datetime.datetime.now() + timedelta(minutes=GestionnaireSécurité.TEMPS_BLOCAGE_MINUTES)
            suivi['bloquee_jusqu_a'] = blocage_jusqu_a.isoformat()
            
            # Enregistrer le blocage dans la sécurité
            if 'journaux_securite' not in db:
                db['journaux_securite'] = []
            
            db['journaux_securite'].append({
                'timestamp': datetime.datetime.now().isoformat(),
                'evenement': 'COMPTE_BLOQUE',
                'identifiant': identifiant,
                'hash_client': hash_client,
                'raison': 'Multiples tentatives de connexion echouees'
            })
        
        sauvegarder_db(db)
        return tracking['attempts']
    
    @staticmethod
    def reset_login_attempts(identifier, request_obj):
        """Reseta as tentativas de login após sucesso"""
        db = charger_db()
        client_hash = SecurityManager.get_client_hash(request_obj)
        login_key = f"login_attempts_{identifier}_{client_hash}"
        
        if 'login_tracking' not in db:
            db['login_tracking'] = {}
        
        if login_key in db['login_tracking']:
            db['login_tracking'][login_key] = {
                'attempts': 0,
                'first_attempt': datetime.datetime.now().isoformat(),
                'locked_until': None
            }
            sauvegarder_db(db)
    
    @staticmethod
    def log_security_event(event_type, details, db=None):
        """Registra eventos de segurança"""
        if db is None:
            db = charger_db()
        
        if 'security_logs' not in db:
            db['security_logs'] = []
        
        db['security_logs'].append({
            'timestamp': datetime.datetime.now().isoformat(),
            'event': event_type,
            'details': details
        })
        sauvegarder_db(db)

# ============================================================
# Configuration de l'application Flask
# ============================================================

app = Flask(__name__)
app.secret_key = 'votre_cle_secrete_ici_changez_moi'  # A changer en production

# Configuration du dossier d'uploads
DOSSIER_UPLOAD = os.path.join(os.path.dirname(__file__), 'uploads')
EXTENSIONS_AUTORISEES = {'jpg', 'jpeg', 'png', 'gif'}

if not os.path.exists(DOSSIER_UPLOAD):
    os.makedirs(DOSSIER_UPLOAD)

app.config['DOSSIER_UPLOAD'] = DOSSIER_UPLOAD
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024  # Limite a 16 MB

# --- Configuration Email / SMTP (a configurer via variables d'environnement) ---
app.config['SMTP_SERVER'] = os.environ.get('SMTP_SERVER', 'smtp.gmail.com')
app.config['SMTP_PORT'] = int(os.environ.get('SMTP_PORT', 587))
app.config['SMTP_USERNAME'] = os.environ.get('SMTP_USERNAME', '')
app.config['SMTP_PASSWORD'] = os.environ.get('SMTP_PASSWORD', '')
app.config['EMAIL_FROM'] = os.environ.get('EMAIL_FROM', app.config['SMTP_USERNAME'])

# Serializer pour tokens d'email
serializer = URLSafeTimedSerializer(app.secret_key)

# ============================================================
# Configuration de la Base de Données (JSON Fallback)
# ============================================================

FICHIER_BASE_DONNEES = os.path.join(os.path.dirname(__file__), 'data.json')

def initialiser_db():
    """Initialise la base de donnees JSON si elle n'existe pas"""
    if not os.path.exists(FICHIER_BASE_DONNEES):
        data = {
            'users': [],
            'documents': [],
            'lost_items': [],
            'admins': []
        }
        with open(FICHIER_BASE_DONNEES, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2, default=str)
    return charger_db()

def charger_db():
    """Charge les données depuis le fichier JSON"""
    try:
        with open(FICHIER_BASE_DONNEES, 'r', encoding='utf-8') as f:
            data = json.load(f)
            # Assurer que toutes les clés existent
            if 'users' not in data:
                data['users'] = []
            if 'documents' not in data:
                data['documents'] = []
            if 'lost_items' not in data:
                data['lost_items'] = []
            if 'admins' not in data:
                data['admins'] = []
            if 'alerts' not in data:
                data['alerts'] = []
            return data
    except:
        return {'users': [], 'documents': [], 'lost_items': [], 'admins': [], 'alerts': []}

def sauvegarder_db(data):
    """Sauvegarde les données dans le fichier JSON"""
    with open(FICHIER_BASE_DONNEES, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2, default=str)

# Initialiser la BD
db = initialiser_db()
print("[OK] Base de donnees JSON initialisee")

# ============================================================
# Security Manager (alias anglais pour les routes admin)
# ============================================================
class SecurityManager:
    MAX_LOGIN_ATTEMPTS = 5
    LOCK_TIME_MINUTES = 30

    @staticmethod
    def validate_password_strength(password):
        if len(password) < 8:
            return False, "Mot de passe trop court (minimum 8 caracteres)"
        if not any(c.isupper() for c in password):
            return False, "Doit contenir au moins une majuscule"
        if not any(c.isdigit() for c in password):
            return False, "Doit contenir au moins un chiffre"
        if not any(c in '!@#$%^&*()-_=+[]{}|;:,.<>?' for c in password):
            return False, "Doit contenir au moins un caractere special"
        return True, "Mot de passe valide"

    @staticmethod
    def get_client_hash(request_obj):
        ip = request_obj.remote_addr or request_obj.headers.get('X-Forwarded-For', 'unknown')
        return hashlib.sha256(ip.encode()).hexdigest()[:16]

    @staticmethod
    def sanitize_input(value):
        if not isinstance(value, str):
            return str(value)
        dangerous = ['<', '>', '"', "'", '&', '%', '\\', '/']
        cleaned = value
        for ch in dangerous:
            cleaned = cleaned.replace(ch, '')
        return cleaned.strip()

    @staticmethod
    def validate_email_format(email):
        import re
        pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
        return re.match(pattern, email) is not None

    @staticmethod
    def validate_password_strength(password):
        if len(password) < 8:
            return False, "Mot de passe trop court (minimum 8 caracteres)"
        if not any(c.isupper() for c in password):
            return False, "Doit contenir au moins une majuscule"
        if not any(c.isdigit() for c in password):
            return False, "Doit contenir au moins un chiffre"
        if not any(c in '!@#$%^&*()-_=+[]{}|;:,.<>?' for c in password):
            return False, "Doit contenir au moins un caractere special"
        return True, "Mot de passe valide"

    @staticmethod
    def log_security_event(event_type, details, db=None):
        if db is None:
            db = charger_db()
        if 'security_logs' not in db:
            db['security_logs'] = []
        db['security_logs'].append({
            'timestamp': datetime.datetime.now().isoformat(),
            'event': event_type,
            'details': details
        })
        sauvegarder_db(db)

    @staticmethod
    def check_login_attempts(identifier, request_obj):
        db = charger_db()
        key = f"login_attempts_{identifier}_{SecurityManager.get_client_hash(request_obj)}"
        tracking = db.get('login_tracking', {})
        entry = tracking.get(key, {
            'attempts': 0,
            'first_attempt': datetime.datetime.now().isoformat(),
            'locked_until': None
        })
        if entry.get('locked_until'):
            locked_until = datetime.datetime.fromisoformat(entry['locked_until'])
            if datetime.datetime.now() < locked_until:
                return False, 'ACCOUNT_LOCKED'
            entry = {'attempts': 0, 'first_attempt': datetime.datetime.now().isoformat(), 'locked_until': None}
        if 'login_tracking' not in db:
            db['login_tracking'] = {}
        db['login_tracking'][key] = entry
        sauvegarder_db(db)
        return True, 'OK'

    @staticmethod
    def record_failed_attempt(identifier, request_obj):
        db = charger_db()
        key = f"login_attempts_{identifier}_{SecurityManager.get_client_hash(request_obj)}"
        if 'login_tracking' not in db:
            db['login_tracking'] = {}
        entry = db['login_tracking'].get(key, {
            'attempts': 0,
            'first_attempt': datetime.datetime.now().isoformat(),
            'locked_until': None
        })
        entry['attempts'] += 1
        if entry['attempts'] >= SecurityManager.MAX_LOGIN_ATTEMPTS:
            entry['locked_until'] = (datetime.datetime.now() + datetime.timedelta(minutes=SecurityManager.LOCK_TIME_MINUTES)).isoformat()
        db['login_tracking'][key] = entry
        SecurityManager.log_security_event('FAILED_LOGIN_ATTEMPT', {'identifier': identifier, 'attempts': entry['attempts']}, db)
        sauvegarder_db(db)
        return entry['attempts']

    @staticmethod
    def reset_login_attempts(identifier, request_obj):
        db = charger_db()
        key = f"login_attempts_{identifier}_{SecurityManager.get_client_hash(request_obj)}"
        if 'login_tracking' not in db:
            db['login_tracking'] = {}
        db['login_tracking'][key] = {
            'attempts': 0,
            'first_attempt': datetime.datetime.now().isoformat(),
            'locked_until': None
        }
        sauvegarder_db(db)

# ============================================================
# Decorators e Middleware de Segurança
# ============================================================

def require_admin(f):
    """Decorator para proteger rotas administrativas"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if not session.get('is_admin'):
            flash("⛔ Accès refusé. Vous devez être administrateur.", 'error')
            return redirect(url_for('admin_login'))
        return f(*args, **kwargs)
    return decorated_function

def require_login(f):
    """Decorator para proteger rotas que requerem login"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if not session.get('user_id'):
            flash("Veuillez vous connecter d'abord.", 'error')
            return redirect(url_for('register'))
        return f(*args, **kwargs)
    return decorated_function

# ============================================================
# Middleware de Segurança Global
# ============================================================

@app.before_request
def add_security_headers():
    """Adiciona headers de segurança a todas as respostas"""
    # Validar sessão e prevenir session fixation
    if session.get('user_id') and not session.get('_session_validated'):
        session['_session_validated'] = True
        session.permanent = True
        app.permanent_session_lifetime = timedelta(hours=24)

@app.after_request
def set_security_headers(response):
    """Configura headers de segurança HTTP"""
    # Prevenir clickjacking
    response.headers['X-Frame-Options'] = 'SAMEORIGIN'
    
    # Prevenir MIME type sniffing
    response.headers['X-Content-Type-Options'] = 'nosniff'
    
    # Prevenir XSS
    response.headers['X-XSS-Protection'] = '1; mode=block'
    response.headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains'
    
    # Content Security Policy
    response.headers['Content-Security-Policy'] = (
        "default-src 'self'; "
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        "style-src 'self' 'unsafe-inline'; "
        "img-src 'self' data:; "
        "font-src 'self'; "
        "connect-src 'self'; "
        "frame-ancestors 'self'"
    )
    
    # Referrer Policy
    response.headers['Referrer-Policy'] = 'strict-origin-when-cross-origin'
    
    return response

# ============================================================
# Fonctions utilitaires
# ============================================================

def allowed_file(filename):
    """Vérifie si le fichier uploadé est un type autorisé"""
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in EXTENSIONS_AUTORISEES

def optimize_image(file_path, max_width=1200, max_height=900, quality=85):
    """Optimise et redimensionne l'image pour le web"""
    try:
        img = Image.open(file_path)
        
        # Convertir RGBA en RGB si nécessaire
        if img.mode in ('RGBA', 'LA', 'P'):
            rgb_img = Image.new('RGB', img.size, (255, 255, 255))
            rgb_img.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
            img = rgb_img
        
        # Redimensionner si nécessaire
        img.thumbnail((max_width, max_height), Image.Resampling.LANCZOS)
        
        # Sauvegarder l'image optimisée
        img.save(file_path, 'JPEG', quality=quality, optimize=True)
        print(f"[OK] Image optimisee: {file_path}")
        return True
    except Exception as e:
        print(f"Erreur optimisation image: {e}")
        return False

def generate_recovery_code():
    """Génère un code de récupération unique"""
    import random
    import string
    code_length = 8
    characters = string.ascii_uppercase + string.digits
    return ''.join(random.choice(characters) for _ in range(code_length))

def is_admin(user_id):
    """Vérifie si un utilisateur est administrateur"""
    db = charger_db()
    for admin in db.get('admins', []):
        if admin.get('user_id') == user_id:
            return admin.get('is_active', True)
    return False

def get_admin_user_ids():
    """Retourne l'ensemble des user_id qui sont administrateurs"""
    db = charger_db()
    return {adm.get('user_id') for adm in db.get('admins', [])}

def create_admin_account(email, password, nom, prenom, creator_id=None):
    """Crée un compte administrateur et l'ajoute à la liste des admins"""
    db = charger_db()
    # Vérifier unicité email
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            raise ValueError("Un utilisateur avec cet email existe déjà.")
    # Vérifier robustesse mot de passe
    is_strong, msg = SecurityManager.validate_password_strength(password)
    if not is_strong:
        raise ValueError(msg)
    user_id = str(ObjectId())
    admin_user = {
        '_id': user_id,
        'email': email,
        'telephone': '',
        'nom': nom,
        'prenom': prenom,
        'password_hash': generate_password_hash(password),
        'is_verified': True,
        'date_creation': datetime.datetime.now().isoformat()
    }
    db['users'].append(admin_user)
    db['admins'].append({
        'user_id': user_id,
        'is_active': True,
        'created_at': datetime.datetime.now().isoformat(),
        'created_by': creator_id
    })
    sauvegarder_db(db)
    return admin_user

def init_default_admin():
    """Initialise un compte administrateur par défaut"""
    db = charger_db()
    
    # Vérifier si un admin existe déjà
    if db.get('admins', []):
        return
    
    # Créer un utilisateur admin par défaut
    admin_user = {
        '_id': str(ObjectId()),
        'email': 'admin@samapiece.com',
        'telephone': '+33612345678',
        'nom': 'Admin',
        'prenom': 'Système',
        'password_hash': generate_password_hash('admin123'),
        'is_verified': True,
        'date_creation': datetime.datetime.now().isoformat()
    }
    
    # Ajouter l'utilisateur
    db['users'].append(admin_user)
    
    # Créer l'entrée administrateur
    admin_entry = {
        'user_id': admin_user['_id'],
        'is_active': True,
        'created_at': datetime.datetime.now().isoformat()
    }
    
    db['admins'].append(admin_entry)
    sauvegarder_db(db)
    
    print("[OK] Administrateur par defaut cree")
    print(f"   Email: admin@samapiece.com")
    print(f"   Mot de passe: admin123")

def get_all_lost_items():
    """Récupère tous les objets perdus"""
    db = charger_db()
    return db.get('lost_items', [])

def get_lost_item_by_id(item_id):
    """Récupère un objet perdu par son ID"""
    db = charger_db()
    for item in db.get('lost_items', []):
        if item.get('_id') == item_id:
            return item
    return None

def create_lost_item(declarant, nom, prenom, numero, reference, date_naissance, lieu_naissance, recompense):
    """Crée un nouvel objet perdu"""
    db = charger_db()
    item = {
        '_id': str(ObjectId()),
        'declarant': declarant,
        'nom': nom,
        'prenom': prenom,
        'numero': numero,
        'reference': reference,
        'status': 'en attente',
        'date_naissance': date_naissance,
        'lieu_naissance': lieu_naissance,
        'recompense': recompense,
        'date_creation': datetime.datetime.now().isoformat(),
        'date_modification': datetime.datetime.now().isoformat(),
        'archived': False
    }
    db['lost_items'].append(item)
    sauvegarder_db(db)
    return item

def update_lost_item_status(item_id, new_status):
    """Met à jour le statut d'un objet perdu"""
    db = charger_db()
    for item in db.get('lost_items', []):
        if item.get('_id') == item_id:
            item['status'] = new_status
            item['date_modification'] = datetime.datetime.now().isoformat()
            if new_status == 'livrer':
                item['archived'] = True
            sauvegarder_db(db)
            return item
    return None

def get_all_users():
    """Récupère la liste de tous les utilisateurs"""
    db = charger_db()
    return db.get('users', [])

def get_user_by_id(user_id):
    """Récupère un utilisateur par son ID"""
    db = charger_db()
    for user in db.get('users', []):
        if user.get('_id') == user_id:
            return user
    return None

def add_user(user_data):
    """Ajoute un nouvel utilisateur"""
    db = charger_db()
    user_id = str(ObjectId())
    user_data['_id'] = user_id
    db['users'].append(user_data)
    sauvegarder_db(db)
    return user_id

def find_user_by_email(email):
    """Trouve un utilisateur par email"""
    db = charger_db()
    for user in db.get('users', []):
        if user.get('email', '').lower() == email.lower():
            return user
    return None

def find_user_by_telephone(telephone):
    """Trouve un utilisateur par numéro de téléphone"""
    db = charger_db()
    for user in db.get('users', []):
        if user.get('telephone', '') == telephone:
            return user
    return None

def verify_user_credentials(identifier, password):
    """Vérifie les identifiants (email ou téléphone) et le mot de passe
    Returns: (user_found, is_valid) tuple"""
    user = find_user_by_email(identifier) or find_user_by_telephone(identifier)
    if not user:
        return None, False
    password_hash = user.get('password_hash', '')
    is_valid = check_password_hash(password_hash, password) if password_hash else False
    return user, is_valid

def send_confirmation_email(to_email, token, fullname):
    """Envoie un email de confirmation contenant le lien de validation"""
    smtp_server = app.config.get('SMTP_SERVER')
    smtp_port = app.config.get('SMTP_PORT')
    smtp_user = app.config.get('SMTP_USERNAME')
    smtp_pass = app.config.get('SMTP_PASSWORD')
    email_from = app.config.get('EMAIL_FROM')

    confirm_url = url_for('confirm_email', token=token, _external=True)
    subject = 'Confirmez votre compte Samapiece'
    html = f"""
    <html>
        <body style='font-family: Arial, sans-serif; color: #2c3e50;'>
            <div style='max-width:600px;margin:0 auto;padding:20px;border-radius:8px;border:1px solid #e6eef8;'>
                <h2 style='color:#2196f3'>Bienvenue, {fullname} 👋</h2>
                <p>Merci d'avoir créé un compte sur <strong>Samapiece</strong>. Avant de pouvoir utiliser votre compte, veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous.</p>
                <p style='text-align:center;margin:30px 0;'>
                    <a href='{confirm_url}' style='display:inline-block;padding:12px 24px;background:#2196f3;color:#fff;border-radius:6px;text-decoration:none;font-weight:700;'>Confirmer mon adresse email</a>
                </p>
                <p>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur:</p>
                <p style='font-size:0.9em;color:#7f8c8d'>{confirm_url}</p>
                <hr/>
                <p style='font-size:0.85em;color:#95a5a6'>Si vous n'avez pas demandé cette inscription, ignorez cet email.</p>
            </div>
        </body>
    </html>
    """
    msg = MIMEText(html, 'html', 'utf-8')
    msg['Subject'] = subject
    msg['From'] = email_from
    msg['To'] = to_email

    try:
        if smtp_port == 465:
            context = ssl.create_default_context()
            with smtplib.SMTP_SSL(smtp_server, smtp_port, context=context) as server:
                if smtp_user and smtp_pass:
                    server.login(smtp_user, smtp_pass)
                server.sendmail(email_from, [to_email], msg.as_string())
        else:
            server = smtplib.SMTP(smtp_server, smtp_port)
            server.ehlo()
            server.starttls(context=ssl.create_default_context())
            server.ehlo()
            if smtp_user and smtp_pass:
                server.login(smtp_user, smtp_pass)
            server.sendmail(email_from, [to_email], msg.as_string())
            server.quit()
        return True
    except Exception as e:
        print(f"Erreur envoi email: {e}")
        return False

def send_reset_email(to_email, token, fullname):
    """Envoie un email contenant le lien de réinitialisation de mot de passe"""
    smtp_server = app.config.get('SMTP_SERVER')
    smtp_port = int(app.config.get('SMTP_PORT', 587))
    smtp_user = app.config.get('SMTP_USERNAME')
    smtp_pass = app.config.get('SMTP_PASSWORD')
    email_from = app.config.get('EMAIL_FROM')

    reset_url = url_for('reset_password', token=token, _external=True)
    subject = 'Réinitialisation du mot de passe - Samapiece'
    html = f"""
    <html>
        <body style='font-family: Arial, sans-serif; color: #2c3e50;'>
            <div style='max-width:600px;margin:0 auto;padding:20px;border-radius:8px;border:1px solid #f0f4f8;'>
                <h2 style='color:#e67e22'>Réinitialisation du mot de passe</h2>
                <p>Bonjour {fullname},</p>
                <p>Vous avez demandé la réinitialisation du mot de passe pour votre compte Samapiece. Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expire dans 1 heure.</p>
                <p style='text-align:center;margin:30px 0;'>
                    <a href='{reset_url}' style='display:inline-block;padding:12px 24px;background:#e67e22;color:#fff;border-radius:6px;text-decoration:none;font-weight:700;'>Réinitialiser mon mot de passe</a>
                </p>
                <p>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur:</p>
                <p style='font-size:0.9em;color:#7f8c8d'>{reset_url}</p>
                <hr/>
                <p style='font-size:0.85em;color:#95a5a6'>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
            </div>
        </body>
    </html>
    """
    msg = MIMEText(html, 'html', 'utf-8')
    msg['Subject'] = subject
    msg['From'] = email_from
    msg['To'] = to_email

    try:
        if smtp_port == 465:
            context = ssl.create_default_context()
            with smtplib.SMTP_SSL(smtp_server, smtp_port, context=context) as server:
                if smtp_user and smtp_pass:
                    server.login(smtp_user, smtp_pass)
                server.sendmail(email_from, [to_email], msg.as_string())
        else:
            server = smtplib.SMTP(smtp_server, smtp_port)
            server.ehlo()
            server.starttls(context=ssl.create_default_context())
            server.ehlo()
            if smtp_user and smtp_pass:
                server.login(smtp_user, smtp_pass)
            server.sendmail(email_from, [to_email], msg.as_string())
            server.quit()
        return True
    except Exception as e:
        print(f"Erreur envoi email reset: {e}")
        return False

def send_email(to_email, subject, html_content):
    """Envoie un email g?n?rique avec contenu HTML"""
    smtp_server = app.config.get('SMTP_SERVER')
    smtp_port = int(app.config.get('SMTP_PORT', 587))
    smtp_user = app.config.get('SMTP_USERNAME')
    smtp_pass = app.config.get('SMTP_PASSWORD')
    email_from = app.config.get('EMAIL_FROM')

    if not smtp_user or not smtp_pass or not email_from:
        print("Configuration SMTP manquante : d?finissez SMTP_USERNAME, SMTP_PASSWORD et EMAIL_FROM.")
        return False

    msg = MIMEText(html_content, 'html', 'utf-8')
    msg['Subject'] = subject
    msg['From'] = email_from
    msg['To'] = to_email

    try:
        if smtp_port == 465:
            context = ssl.create_default_context()
            with smtplib.SMTP_SSL(smtp_server, smtp_port, context=context) as server:
                server.login(smtp_user, smtp_pass)
                server.sendmail(email_from, [to_email], msg.as_string())
        else:
            server = smtplib.SMTP(smtp_server, smtp_port)
            server.ehlo()
            server.starttls(context=ssl.create_default_context())
            server.ehlo()
            server.login(smtp_user, smtp_pass)
            server.sendmail(email_from, [to_email], msg.as_string())
            server.quit()
        return True
    except Exception as e:
        print(f"Erreur envoi email: {e}")
        return False

def find_documents(query):
    """Recherche des documents selon les critères"""
    db = charger_db()
    results = []
    for doc in db.get('documents', []):
        match = True
        for key, value in query.items():
            if key not in doc:
                match = False
                break
            
            # Comparaison insensible à la casse pour les champs texte
            doc_value = str(doc[key]).strip().lower()
            search_value = str(value).strip().lower()
            
            if doc_value != search_value:
                match = False
                break
        if match:
            results.append(doc)
    return results

def get_user_documents(user_id):
    """Récupère tous les documents déclarés par un utilisateur"""
    db = charger_db()
    user_docs = []
    for doc in db.get('documents', []):
        if doc.get('user_id') == user_id:
            user_docs.append(doc)
    return user_docs

def document_exists(type_piece, nom, prenom, date_naissance, lieu_naissance):
    """Vérifie si un document avec les mêmes infos existe déjà"""
    db = charger_db()
    for doc in db.get('documents', []):
        if (doc.get('type_piece', '').lower() == type_piece.lower() and
            doc.get('nom', '').lower() == nom.lower() and
            doc.get('prenom', '').lower() == prenom.lower() and
            doc.get('date_naissance', '') == date_naissance and
            doc.get('lieu_naissance', '').lower() == lieu_naissance.lower()):
            return True
    return False

def create_alert(email, type_piece, nom, prenom, date_naissance, lieu_naissance):
    """Crée une alerte pour notifier quand un document correspondant est déclaré"""
    db = charger_db()
    alert = {
        '_id': str(uuid.uuid4()),
        'email': email,
        'type_piece': type_piece,
        'nom': nom,
        'prenom': prenom,
        'date_naissance': date_naissance,
        'lieu_naissance': lieu_naissance,
        'date_creation': datetime.datetime.now().isoformat(),
        'is_active': True
    }
    if 'alerts' not in db:
        db['alerts'] = []
    db['alerts'].append(alert)
    sauvegarder_db(db)
    return alert

def get_matching_alerts(type_piece, nom, prenom, date_naissance, lieu_naissance):
    """Récupère les alertes qui correspondent à un nouveau document"""
    db = charger_db()
    matching_alerts = []
    for alert in db.get('alerts', []):
        if not alert.get('is_active'):
            continue
        if (alert.get('type_piece', '').lower() == type_piece.lower() and
            alert.get('nom', '').lower() == nom.lower() and
            alert.get('prenom', '').lower() == prenom.lower() and
            alert.get('date_naissance', '') == date_naissance and
            alert.get('lieu_naissance', '').lower() == lieu_naissance.lower()):
            matching_alerts.append(alert)
    return matching_alerts

def send_alert_notification(email, document_info):
    """Envoie un email de notification d'alerte"""
    try:
        subject = f"Samapiece - Document trouve : {document_info.get('type_piece', '')}"
        html_content = f"""
        <html>
            <body style="font-family: Arial, sans-serif; background-color: #f5f5f5;">
                <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px;">
                    <h2 style="color: #2c3e50;">Bonnes nouvelles !</h2>
                    <p>Un document correspondant a vos criteres de recherche a ete declare :</p>
                    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p><strong>Type :</strong> {document_info.get('type_piece', 'N/A')}</p>
                        <p><strong>Nom :</strong> {document_info.get('nom', 'N/A')}</p>
                        <p><strong>Prenom :</strong> {document_info.get('prenom', 'N/A')}</p>
                        <p><strong>Date de naissance :</strong> {document_info.get('date_naissance', 'N/A')}</p>
                        <p><strong>Lieu de naissance :</strong> {document_info.get('lieu_naissance', 'N/A')}</p>
                    </div>
                    <p><a href="{url_for('home', _external=True)}" style="background-color: #3498db; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Voir le site</a></p>
                    <p style="color: #7f8c8d; font-size: 0.9em; margin-top: 30px;">Samapiece - Service de rappel de documents officiels</p>
                </div>
            </body>
        </html>
        """
        result = send_email(email, subject, html_content)
        if not result:
            print(f"Envoi email alerte echoue pour {email}. Verifiez la configuration SMTP.")
        return result
    except Exception as e:
        print(f"Erreur lors de l'envoi de la notification: {e}")
        return False
# ============================================================
# Routes
# ============================================================

@app.route('/')
def home():
    """Route d'accueil - affiche la page d'accueil de Samapiece"""
    return render_template('home.html')

@app.route('/register', methods=['GET', 'POST'])
def register():
    """Page unifiée de connexion/enregistrement"""
    if request.method == 'GET':
        return render_template('register.html')

    action = request.form.get('action', 'login').strip()

    if action == 'login':
        login_identifier = request.form.get('login_identifier', '').strip()
        password = request.form.get('password', '').strip()

        if not login_identifier or not password:
            flash("Veuillez entrer votre email/téléphone et votre mot de passe.", 'error')
            return render_template('register.html')

        user, is_valid = verify_user_credentials(login_identifier, password)

        if not user or not is_valid:
            flash("Email/téléphone ou mot de passe incorrect.", 'error')
            return render_template('register.html')

        if not user.get('is_verified', False):
            flash("Votre adresse email n'a pas été confirmée. Vérifiez votre boîte mail.", 'warning')
            return render_template('register.html')

        session['user_id'] = user['_id']
        session['user_name'] = f"{user['nom']} {user['prenom']}"

        flash(f"Connecté en tant que {user['nom']} {user['prenom']}!", 'success')
        return redirect(url_for('dashboard'))

    else:
        nom = request.form.get('nom', '').strip()
        prenom = request.form.get('prenom', '').strip()
        email = request.form.get('email', '').strip()
        telephone = request.form.get('telephone', '').strip()
        new_password = request.form.get('new_password', '').strip()
        confirm_password = request.form.get('confirm_password', '').strip()

        errors = []

        if not nom:
            errors.append("Le nom est obligatoire.")
        if not prenom:
            errors.append("Le prénom est obligatoire.")
        if not email:
            errors.append("L'adresse email est obligatoire pour créer un compte.")
        if not new_password:
            errors.append("Le mot de passe est obligatoire.")
        if len(new_password) < 6:
            errors.append("Le mot de passe doit contenir au moins 6 caractères.")
        if new_password != confirm_password:
            errors.append("Les mots de passe ne correspondent pas.")

        if email and find_user_by_email(email):
            errors.append("Cet email est déjà utilisé.")
        if telephone and find_user_by_telephone(telephone):
            errors.append("Ce numéro de téléphone est déjà utilisé.")

        if errors:
            for error in errors:
                flash(error, 'error')
            return render_template('register.html')

        new_user = {
            'nom': nom,
            'prenom': prenom,
            'email': email,
            'telephone': telephone if telephone else None,
            'password_hash': generate_password_hash(new_password),
            'date_creation': datetime.datetime.now().isoformat(),
            'is_verified': False
        }

        user_id = add_user(new_user)

        token = serializer.dumps(email, salt='email-confirm')
        sent = send_confirmation_email(email, token, f"{nom} {prenom}")

        if not sent:
            db = charger_db()
            db['users'] = [u for u in db.get('users', []) if u.get('_id') != user_id]
            sauvegarder_db(db)
            flash("Impossible d'envoyer l'email de confirmation. Vérifiez la configuration SMTP.", 'error')
            return render_template('register.html')

        flash("Un email de confirmation a été envoyé. Veuillez vérifier votre boîte mail avant de vous connecter.", 'success')
        return render_template('register.html')

@app.route('/confirm/<token>')
def confirm_email(token):
    """Valide l'adresse email via token envoyé par email"""
    try:
        email = serializer.loads(token, salt='email-confirm', max_age=60*60*24)
    except SignatureExpired:
        flash("Le lien de confirmation a expiré. Veuillez vous réinscrire.", 'error')
        return redirect(url_for('register'))
    except BadSignature:
        flash("Lien de confirmation invalide.", 'error')
        return redirect(url_for('register'))

    user = find_user_by_email(email)
    if not user:
        flash("Utilisateur introuvable pour cette adresse.", 'error')
        return redirect(url_for('register'))

    db = charger_db()
    updated = False
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            u['is_verified'] = True
            updated = True
            break
    if updated:
        sauvegarder_db(db)
        flash("Adresse email confirmée. Vous pouvez maintenant vous connecter.", 'success')
        session['user_id'] = user.get('_id')
        session['user_name'] = f"{user.get('nom')} {user.get('prenom')}"
        return redirect(url_for('dashboard'))

    flash("Impossible de vérifier votre compte.", 'error')
    return redirect(url_for('register'))

@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():
    """Demande de réinitialisation du mot de passe"""
    if request.method == 'GET':
        return render_template('forgot_password.html')

    email = request.form.get('email', '').strip()
    if not email:
        flash('Veuillez entrer votre adresse email.', 'error')
        return render_template('forgot_password.html')

    user = find_user_by_email(email)
    if not user:
        flash('Si un compte existe pour cet email, un message a été envoyé.', 'success')
        return render_template('forgot_password.html')

    token = serializer.dumps(email, salt='password-reset')
    sent = send_reset_email(email, token, f"{user.get('nom')} {user.get('prenom')}")
    if not sent:
        flash('Impossible d\'envoyer l\'email. Vérifiez la configuration SMTP.', 'error')
        return render_template('forgot_password.html')

    flash('Si un compte existe pour cet email, un message a été envoyé.', 'success')
    return render_template('forgot_password.html')

@app.route('/reset/<token>', methods=['GET', 'POST'])
def reset_password(token):
    """Page de réinitialisation de mot de passe via token"""
    try:
        email = serializer.loads(token, salt='password-reset', max_age=60*60)
    except SignatureExpired:
        flash('Le lien de réinitialisation a expiré. Demandez-en un nouveau.', 'error')
        return redirect(url_for('forgot_password'))
    except BadSignature:
        flash('Lien de réinitialisation invalide.', 'error')
        return redirect(url_for('forgot_password'))

    if request.method == 'GET':
        return render_template('reset_password.html', token=token)

    new_password = request.form.get('new_password', '').strip()
    confirm_password = request.form.get('confirm_password', '').strip()

    if not new_password or len(new_password) < 6:
        flash('Le mot de passe doit contenir au moins 6 caractères.', 'error')
        return render_template('reset_password.html', token=token)
    if new_password != confirm_password:
        flash('Les mots de passe ne correspondent pas.', 'error')
        return render_template('reset_password.html', token=token)

    db = charger_db()
    updated = False
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            u['password_hash'] = generate_password_hash(new_password)
            u['is_verified'] = True
            updated = True
            break

    if updated:
        sauvegarder_db(db)
        flash('Mot de passe mis à jour avec succès. Vous êtes connecté.', 'success')
        user = find_user_by_email(email)
        session['user_id'] = user.get('_id')
        session['user_name'] = f"{user.get('nom')} {user.get('prenom')}"
        return redirect(url_for('dashboard'))

    flash('Impossible de trouver le compte.', 'error')
    return redirect(url_for('forgot_password'))

@app.route('/declare', methods=['GET', 'POST'])
def declare():
    """Route de déclaration - déclare un document trouvé"""
    if 'user_id' not in session:
        flash("✋ Pour déclarer un document, vous devez d'abord créer un compte ou vous connecter.", 'warning')
        return redirect(url_for('register'))
    
    types_documents = [
        'Carte d\'identité',
        'Passeport',
        'Permis de conduire',
        'Permis de séjour',
        'Carte consulaire',
        'Autre'
    ]
    
    if request.method == 'GET':
        current_user = get_user_by_id(session['user_id'])
        return render_template('declare.html', 
                             types_documents=types_documents,
                             current_user=current_user)
    
    type_pieces = [t.strip() for t in request.form.getlist('type_piece') if t.strip()]
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    
    errors = []
    
    if not type_pieces:
        errors.append("Choisissez au moins un type de document.")
    elif len(type_pieces) > 3:
        errors.append("Vous pouvez choisir au maximum 3 documents.")
    if not nom:
        errors.append("Le nom est obligatoire.")
    if not prenom:
        errors.append("Le prénom est obligatoire.")
    if not date_naissance:
        errors.append("La date de naissance est obligatoire.")
    if not lieu_naissance:
        errors.append("Le lieu de naissance est obligatoire.")
    
    if not errors:
        for tp in type_pieces:
            if document_exists(tp, nom, prenom, date_naissance, lieu_naissance):
                errors.append(f'Le document "{tp}" a déjà été déclaré dans le système.')
                break
    
    if 'photo_piece' not in request.files:
        errors.append("Veuillez télécharger une image du document.")
    else:
        file = request.files['photo_piece']
        if file.filename == '':
            errors.append("Veuillez sélectionner un fichier image.")
        elif not allowed_file(file.filename):
            errors.append("Format de fichier non autorisé. Utilisez JPG, PNG ou GIF.")
    
    if errors:
        current_user = get_user_by_id(session['user_id'])
        for error in errors:
            flash(error, 'error')
        return render_template('declare.html',
                             types_documents=types_documents,
                             current_user=current_user)
    
    user_obj_id = session['user_id']
    if not get_user_by_id(user_obj_id):
        flash("Votre compte n'existe plus.", 'error')
        session.clear()
        return redirect(url_for('register'))
    
    file = request.files['photo_piece']
    
    doc_id = str(ObjectId())
    file_extension = file.filename.rsplit('.', 1)[1].lower()
    filename = secure_filename(f"{doc_id}.{file_extension}")
    filepath = os.path.join(app.config['DOSSIER_UPLOAD'], filename)
    file.save(filepath)
    
    # Optimiser l'image
    optimize_image(filepath, max_width=1200, max_height=900, quality=85)
    
    db = charger_db()
    created_documents = []
    for idx, tp in enumerate(type_pieces):
        current_doc_id = str(ObjectId()) if idx > 0 else doc_id
        new_document = {
            '_id': current_doc_id,
            'type_piece': tp,
            'nom': nom,
            'prenom': prenom,
            'date_naissance': date_naissance,
            'lieu_naissance': lieu_naissance,
            'photo_path': f'uploads/{filename}',
            'user_id': user_obj_id,
            'date_declaration': datetime.datetime.now().isoformat(),
            'recovery_code': generate_recovery_code()
        }
        db['documents'].append(new_document)
        created_documents.append(new_document)
    
    sauvegarder_db(db)
    
    # Vérifier et notifier les alertes correspondantes
    for new_document in created_documents:
        matching_alerts = get_matching_alerts(new_document['type_piece'], nom, prenom, date_naissance, lieu_naissance)
        for alert in matching_alerts:
            send_alert_notification(alert['email'], new_document)
    
    return redirect(url_for('declaration_success'))

@app.route('/declaration-success')
def declaration_success():
    """Page de succès après déclaration"""
    return render_template('success.html',
                         message="Document déclaré avec succès!",
                         next_action_url=url_for('home'),
                         next_action_text="Retour à l'accueil")

@app.route('/search', methods=['GET', 'POST'])
def search():
    """Route de recherche - recherche les documents déclarés"""
    if request.method == 'GET':
        return render_template('search.html')
    
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    
    if not nom and not prenom and not date_naissance and not lieu_naissance:
        flash("Veuillez remplir au moins un champ de recherche.", 'error')
        return render_template('search.html')
    
    query = {}
    if nom:
        query['nom'] = nom
    if prenom:
        query['prenom'] = prenom
    if date_naissance:
        query['date_naissance'] = date_naissance
    if lieu_naissance:
        query['lieu_naissance'] = lieu_naissance
    
    results = find_documents(query)
    
    return render_template('results.html', 
                         results=results, 
                         query_count=len(results),
                         search_query=query,
                         nom=nom,
                         prenom=prenom,
                         date_naissance=date_naissance,
                         lieu_naissance=lieu_naissance)

@app.route('/uploads/<filename>')
def uploaded_file(filename):
    """Servir directement les fichiers uploadés depuis le dossier uploads"""
    try:
        return send_from_directory(app.config['DOSSIER_UPLOAD'], filename, as_attachment=False)
    except FileNotFoundError:
        flash('Image non trouvée', 'error')
        return redirect(url_for('home'))

@app.route('/dashboard')
def dashboard():
    """Tableau de bord utilisateur"""
    user_id = session.get('user_id')
    
    if not user_id:
        flash("Veuillez vous connecter d'abord.", 'error')
        return redirect(url_for('register'))
    
    user = get_user_by_id(user_id)
    if not user:
        flash("Utilisateur non trouvé.", 'error')
        return redirect(url_for('register'))
    
    user_documents = get_user_documents(user_id)
    
    return render_template('dashboard.html', 
                         user=user, 
                         documents=user_documents,
                         document_count=len(user_documents))

@app.route('/profile')
def profile():
    """Afficher le profil de l'utilisateur connecté"""
    user_id = session.get('user_id')
    
    if not user_id:
        flash("Veuillez vous connecter d'abord.", 'error')
        return redirect(url_for('register'))
    
    user = get_user_by_id(user_id)
    if not user:
        flash("Utilisateur non trouvé.", 'error')
        return redirect(url_for('register'))
    
    user_documents = get_user_documents(user_id)
    
    return render_template('profile.html', user=user, user_documents=user_documents)

@app.route('/logout')
def logout():
    """Déconnexion de l'utilisateur"""
    session.clear()
    flash("Vous êtes déconnecté.", 'success')
    return redirect(url_for('home'))

@app.route('/create_alert', methods=['POST'])
def create_alert_route():
    """Crée une alerte pour notifier quand un document correspondant est déclaré"""
    email = request.form.get('email', '').strip()
    type_piece = request.form.get('type_piece', '').strip()
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    
    # Validation basique
    if not email or '@' not in email:
        return jsonify({'success': False, 'message': 'Email invalide'}), 400
    
    if not all([type_piece, nom, prenom, date_naissance, lieu_naissance]):
        return jsonify({'success': False, 'message': 'Tous les champs sont obligatoires'}), 400
    
    try:
        alert = create_alert(email, type_piece, nom, prenom, date_naissance, lieu_naissance)
        # Envoyer un email de confirmation
        subject = "✅ Alerte créée - Samapiece"
        html_content = f"""
        <html>
            <body style="font-family: Arial, sans-serif; background-color: #f5f5f5;">
                <div style="max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px;">
                    <h2 style="color: #2c3e50;">✅ Alerte créée avec succès!</h2>
                    <p>Vous serez notifié par email dès qu'un document correspondant à vos critères sera déclaré:</p>
                    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p><strong>Type:</strong> {type_piece}</p>
                        <p><strong>Nom:</strong> {nom}</p>
                        <p><strong>Prénom:</strong> {prenom}</p>
                        <p><strong>Date de naissance:</strong> {date_naissance}</p>
                        <p><strong>Lieu de naissance:</strong> {lieu_naissance}</p>
                    </div>
                    <p style="color: #7f8c8d; font-size: 0.9em;">Samapiece - Service de rappel de documents officiels</p>
                </div>
            </body>
        </html>
        """
        send_email(email, subject, html_content)
        return jsonify({'success': True, 'message': 'Alerte créée! Un email de confirmation a été envoyé.'}), 200
    except Exception as e:
        print(f"Erreur lors de la création de l'alerte: {e}")
        return jsonify({'success': False, 'message': 'Erreur lors de la création de l\'alerte'}), 500

@app.route('/recover_document', methods=['POST'])
def recover_document():
    """Récupérer un document via code de récupération"""
    user_id = session.get('user_id')
    
    if not user_id:
        flash("Veuillez vous connecter d'abord.", 'error')
        return redirect(url_for('register'))
    
    recovery_code = request.form.get('recovery_code', '').strip().upper()
    
    if not recovery_code:
        flash("Veuillez entrer un code de récupération.", 'error')
        return redirect(url_for('profile'))
    
    db = charger_db()
    found_document = None
    
    for doc in db.get('documents', []):
        if doc.get('recovery_code', '').upper() == recovery_code:
            found_document = doc
            break
    
    if not found_document:
        flash("Code de récupération invalide. Vérifiez le code et réessayez.", 'error')
        return redirect(url_for('profile'))
    
    owner_contact = found_document.get('contact', 'Non spécifié')
    owner_name = found_document.get('nom_proprietaire', 'Propriétaire')
    
    flash(f"✓ Document trouvé! Le propriétaire {owner_name} sera contacté via: {owner_contact}", 'success')
    return redirect(url_for('profile'))

# ============================================================
# Routes Administration
# ============================================================

@app.route('/admin/login', methods=['GET', 'POST'])
def admin_login():
    """Page de connexion pour les administrateurs avec proteção contra força bruta"""
    if request.method == 'POST':
        email = request.form.get('email', '').strip()
        password = request.form.get('password', '').strip()
        
        if not email or not password:
            flash("Veuillez remplir tous les champs.", 'error')
            return redirect(url_for('admin_login'))
        
        # Verificar se a conta está bloqueada
        can_attempt, status = SecurityManager.check_login_attempts(email, request)
        
        if status == 'ACCOUNT_LOCKED':
            flash(f"⛔ Compte bloqueado por segurança. Tentativas múltiplas de login detectadas.\n"
                  f"Veuillez réinitialiser votre mot de passe ou contacter un administrateur.", 'error')
            SecurityManager.log_security_event('ACCOUNT_LOCKED_ATTEMPT', {
                'email': email,
                'ip': request.remote_addr
            })
            return redirect(url_for('admin_login'))
        
        if not can_attempt:
            flash("⛔ Trop de tentativas. Veuillez réessayer plus tard.", 'error')
            return redirect(url_for('admin_login'))
        
        user, is_valid = verify_user_credentials(email, password)
        
        if user and is_valid and is_admin(user.get('_id')):
            # Reset de tentativas após sucesso
            SecurityManager.reset_login_attempts(email, request)
            SecurityManager.log_security_event('ADMIN_LOGIN_SUCCESS', {
                'admin_id': user.get('_id'),
                'email': email,
                'ip': request.remote_addr
            })
            
            session['user_id'] = user.get('_id')
            session['is_admin'] = True
            flash(f"✓ Bienvenue {user.get('nom', 'Admin')}!", 'success')
            return redirect(url_for('admin_dashboard'))
        else:
            # Registrar tentativa falhada
            attempts = SecurityManager.record_failed_attempt(email, request)
            remaining = SecurityManager.MAX_LOGIN_ATTEMPTS - attempts
            
            SecurityManager.log_security_event('FAILED_LOGIN_ATTEMPT', {
                'email': email,
                'attempts': attempts,
                'ip': request.remote_addr
            })
            
            if remaining > 0:
                flash(f"❌ Identifiants invalides. ({remaining} tentatives restantes)", 'error')
            else:
                flash(f"⛔ Compte bloqueado! Trop de tentatives échouées. "
                      f"Veuillez réinitialiser votre mot de passe.", 'error')
            
            return redirect(url_for('admin_login'))
    
    return render_template('admin/login.html')

@app.route('/admin/dashboard')
def admin_dashboard():
    """Tableau de bord administrateur"""
    if not session.get('is_admin'):
        flash("Accès refusé. Vous devez être administrateur.", 'error')
        return redirect(url_for('admin_login'))
    
    user_id = session.get('user_id')
    user = get_user_by_id(user_id)
    
    if not user:
        session.clear()
        flash("Utilisateur non trouvé.", 'error')
        return redirect(url_for('admin_login'))
    
    lost_items = get_all_lost_items()
    active_items = [item for item in lost_items if not item.get('archived', False)]
    archived_items = [item for item in lost_items if item.get('archived', False)]
    
    stats = {
        'total_items': len(active_items),
        'pending': len([item for item in active_items if item.get('status') == 'en attente']),
        'in_progress': len([item for item in active_items if item.get('status') == 'en cours']),
        'delivered': len([item for item in active_items if item.get('status') == 'livrer']),
        'archived': len(archived_items),
        'total_users': len(get_all_users())
    }
    
    return render_template('admin/dashboard.html', user=user, stats=stats)

@app.route('/admin/lost-items')
def admin_lost_items():
    """Gestion des objets perdus"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user_id = session.get('user_id')
    user = get_user_by_id(user_id)
    
    status_filter = request.args.get('status', 'all')
    lost_items = get_all_lost_items()
    
    if status_filter != 'all':
        if status_filter == 'archived':
            lost_items = [item for item in lost_items if item.get('archived', False)]
        else:
            lost_items = [item for item in lost_items if not item.get('archived', False) and item.get('status') == status_filter]
    else:
        lost_items = [item for item in lost_items if not item.get('archived', False)]
    
    return render_template('admin/lost_items.html', user=user, items=lost_items, current_filter=status_filter)

@app.route('/admin/lost-items/<item_id>', methods=['GET', 'POST'])
def admin_edit_lost_item(item_id):
    """Éditer un objet perdu"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    item = get_lost_item_by_id(item_id)
    
    if not item:
        flash("Objet non trouvé.", 'error')
        return redirect(url_for('admin_lost_items'))
    
    if request.method == 'POST':
        new_status = request.form.get('status', item.get('status'))
        
        update_lost_item_status(item_id, new_status)
        flash(f"✓ Statut de l'objet mise à jour: {new_status}", 'success')
        return redirect(url_for('admin_lost_items'))
    
    return render_template('admin/edit_lost_item.html', user=user, item=item)

@app.route('/admin/users')
def admin_users():
    """Gestion des utilisateurs"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    admin_ids = get_admin_user_ids()
    # On ne liste ici que les utilisateurs non-admin
    users = [u for u in get_all_users() if u.get('_id') not in admin_ids]
    
    # Ajouter des infos supplémentaires aux utilisateurs
    for u in users:
        u['date_creation'] = u.get('date_creation', 'N/A')
        u['statut'] = 'actif' if u.get('is_verified', False) else 'inactif'
        u['type_contact'] = 'email' if u.get('email') else 'téléphone'
    
    return render_template('admin/users.html', user=user, users=users)

@app.route('/admin/users/<user_id>')
def admin_user_detail(user_id):
    """Détail d'un utilisateur"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    admin_user = get_user_by_id(session.get('user_id'))
    target_user = get_user_by_id(user_id)
    
    # Empêcher l'accès aux fiches des admins via cet écran
    if target_user and target_user.get('_id') in get_admin_user_ids():
        flash("Les comptes administrateur sont gérés dans l'espace personnel dédié.", 'error')
        return redirect(url_for('admin_users'))
    
    if not target_user:
        flash("Utilisateur non trouvé.", 'error')
        return redirect(url_for('admin_users'))
    
    user_documents = get_user_documents(user_id)
    
    return render_template('admin/user_detail.html', admin_user=admin_user, user=target_user, documents=user_documents)

@app.route('/admin/create-admin', methods=['GET', 'POST'])
def admin_create_admin():
    """Création d'un autre administrateur (réservé admin connecté)"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    current_admin = get_user_by_id(session.get('user_id'))
    
    if request.method == 'POST':
        nom = request.form.get('nom', '').strip()
        prenom = request.form.get('prenom', '').strip()
        email = request.form.get('email', '').strip()
        password = request.form.get('password', '').strip()
        confirm = request.form.get('confirm_password', '').strip()
        
        if not all([nom, prenom, email, password, confirm]):
            flash("Tous les champs sont obligatoires.", 'error')
            return redirect(url_for('admin_create_admin'))
        if password != confirm:
            flash("Les mots de passe ne correspondent pas.", 'error')
            return redirect(url_for('admin_create_admin'))
        if not SecurityManager.validate_email_format(email):
            flash("Email invalide.", 'error')
            return redirect(url_for('admin_create_admin'))
        try:
            create_admin_account(email, password, nom, prenom, creator_id=current_admin.get('_id'))
            flash(f"Administrateur {prenom} {nom} créé avec succès.", 'success')
            return redirect(url_for('admin_users'))
        except ValueError as ve:
            flash(str(ve), 'error')
            return redirect(url_for('admin_create_admin'))
        except Exception as e:
            print(f"Erreur création admin: {e}")
            flash("Erreur lors de la création de l'administrateur.", 'error')
            return redirect(url_for('admin_create_admin'))
    
    return render_template('admin/create_admin.html', user=current_admin)

@app.route('/admin/lost-items/new', methods=['GET', 'POST'])
def admin_create_lost_item():
    """Créer un nouvel objet perdu"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    
    if request.method == 'POST':
        try:
            declarant = request.form.get('declarant', '').strip()
            nom = request.form.get('nom', '').strip()
            prenom = request.form.get('prenom', '').strip()
            numero = request.form.get('numero', '').strip()
            reference = request.form.get('reference', '').strip()
            date_naissance = request.form.get('date_naissance', '').strip()
            lieu_naissance = request.form.get('lieu_naissance', '').strip()
            recompense = request.form.get('recompense', '').strip()
            
            if not all([declarant, nom, prenom, numero, reference, date_naissance, lieu_naissance]):
                flash("Veuillez remplir tous les champs obligatoires.", 'error')
                return redirect(url_for('admin_create_lost_item'))
            
            item = create_lost_item(declarant, nom, prenom, numero, reference, date_naissance, lieu_naissance, recompense)
            flash(f"✓ Objet perdu créé avec succès! ID: {item['reference']}", 'success')
            return redirect(url_for('admin_lost_items'))
        except Exception as e:
            print(f"Erreur création objet perdu: {e}")
            flash(f"Erreur lors de la création de l'objet.", 'error')
            return redirect(url_for('admin_create_lost_item'))
    
    return render_template('admin/create_lost_item.html', user=user)

@app.route('/admin/forgot-password', methods=['GET', 'POST'])
def admin_forgot_password():
    """Formulário para recuperação de senha"""
    if request.method == 'POST':
        email = request.form.get('email', '').strip()
        
        if not email:
            flash("Veuillez entrer votre email.", 'error')
            return redirect(url_for('admin_forgot_password'))
        
        # Valider email format
        if not SecurityManager.validate_email_format(email):
            flash("Format d'email invalide.", 'error')
            return redirect(url_for('admin_forgot_password'))
        
        user = find_user_by_email(email)
        
        if not user or not is_admin(user.get('_id')):
            # Não revelar se o usuário existe (segurança)
            flash("Si ce compte existe, un email de réinitialisation sera envoyé.", 'success')
            SecurityManager.log_security_event('PASSWORD_RESET_REQUEST', {
                'email': email,
                'ip': request.remote_addr,
                'user_found': user is not None
            })
            return redirect(url_for('admin_login'))
        
        # Gerar token de reset
        try:
            token = serializer.dumps(user.get('_id'), salt='password-reset-salt')
            reset_url = url_for('admin_reset_password', token=token, _external=True)
            
            subject = "🔐 Réinitialisation du mot de passe - Samapiece Admin"
            html_content = f"""
            <html>
                <body style="font-family: Arial, sans-serif;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                        <h2 style="color: #667eea;">Réinitialisation du mot de passe</h2>
                        <p>Vous avez demandé une réinitialisation de mot de passe.</p>
                        <p style="text-align: center; margin: 30px 0;">
                            <a href="{reset_url}" style="display: inline-block; padding: 12px 30px; 
                            background: #667eea; color: white; text-decoration: none; border-radius: 6px; 
                            font-weight: bold;">Réinitialiser mon mot de passe</a>
                        </p>
                        <p>Ce lien expire dans 1 heure.</p>
                        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
                        <p style="color: #999; font-size: 0.9em;">
                            Si vous n'avez pas demandé cela, ignorez cet email.
                        </p>
                    </div>
                </body>
            </html>
            """
            
            send_email(email, subject, html_content)
            flash("✓ Email de réinitialisation envoyé. Vérifiez votre boîte de réception.", 'success')
            
            SecurityManager.log_security_event('PASSWORD_RESET_EMAIL_SENT', {
                'user_id': user.get('_id'),
                'email': email
            })
            
            return redirect(url_for('admin_login'))
        except Exception as e:
            print(f"Erreur lors de l'envoi de l'email: {e}")
            flash("Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard.", 'error')
            return redirect(url_for('admin_forgot_password'))
    
    return render_template('admin/forgot_password.html')

@app.route('/admin/reset-password/<token>', methods=['GET', 'POST'])
def admin_reset_password(token):
    """Réinitialiser le mot de passe avec token"""
    try:
        user_id = serializer.loads(token, salt='password-reset-salt', max_age=3600)  # 1 heure
    except SignatureExpired:
        flash("⛔ Lien d'réinitialisation expiré. Veuillez demander un nouveau lien.", 'error')
        return redirect(url_for('admin_forgot_password'))
    except BadSignature:
        flash("⛔ Lien invalide.", 'error')
        return redirect(url_for('admin_forgot_password'))
    
    if request.method == 'POST':
        new_password = request.form.get('new_password', '').strip()
        confirm_password = request.form.get('confirm_password', '').strip()
        
        # Validar senhas
        if not new_password or not confirm_password:
            flash("Veuillez remplir tous les champs.", 'error')
            return redirect(url_for('admin_reset_password', token=token))
        
        if new_password != confirm_password:
            flash("Les mots de passe ne correspondent pas.", 'error')
            return redirect(url_for('admin_reset_password', token=token))
        
        # Vérifier force de senha
        is_strong, message = SecurityManager.validate_password_strength(new_password)
        if not is_strong:
            flash(f"❌ Mot de passe faible: {message}", 'error')
            return redirect(url_for('admin_reset_password', token=token))
        
        # Atualizar password
        db = charger_db()
        user = get_user_by_id(user_id)
        
        if not user:
            flash("Utilisateur non trouvé.", 'error')
            return redirect(url_for('admin_login'))
        
        # Encontrar e atualizar usuário
        for u in db['users']:
            if u.get('_id') == user_id:
                u['password_hash'] = generate_password_hash(new_password)
                u['updated_at'] = datetime.datetime.now().isoformat()
                break
        
        # Limpar bloqueios de login
        if 'login_tracking' in db:
            keys_to_delete = [k for k in db['login_tracking'].keys() if user_id in k]
            for k in keys_to_delete:
                del db['login_tracking'][k]
        
        sauvegarder_db(db)
        
        SecurityManager.log_security_event('PASSWORD_RESET_SUCCESS', {
            'user_id': user_id,
            'email': user.get('email')
        })
        
        flash("✓ Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.", 'success')
        return redirect(url_for('admin_login'))
    
    return render_template('admin/reset_password.html', token=token)

@app.route('/admin/documents-perdus')
def admin_documents_perdus():
    """Affiche les documents perdus (déclarés) avec possibilité de voir les détails"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    db = charger_db()
    
    # Récupérer tous les documents déclarés
    documents = db.get('documents', [])
    
    # Enrichir les informations des documents
    documents_enrichis = []
    for doc in documents:
        # Trouver le déclarant
        declarant = get_user_by_id(doc.get('user_id', ''))
        doc_enrichi = doc.copy()
        doc_enrichi['declarant_nom'] = declarant.get('nom', 'Unknown') if declarant else 'Unknown'
        doc_enrichi['declarant_prenom'] = declarant.get('prenom', '') if declarant else ''
        doc_enrichi['declarant_email'] = declarant.get('email', 'N/A') if declarant else 'N/A'
        doc_enrichi['declarant_telephone'] = declarant.get('telephone', 'N/A') if declarant else 'N/A'
        documents_enrichis.append(doc_enrichi)
    
    return render_template('admin/documents_perdus.html', user=user, documents=documents_enrichis)

@app.route('/admin/documents-perdus/<doc_id>')
def admin_document_detail(doc_id):
    """Affiche les détails complets d'un document perdu"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    db = charger_db()
    
    # Trouver le document
    document = None
    for doc in db.get('documents', []):
        if doc.get('_id') == doc_id:
            document = doc
            break
    
    if not document:
        flash("Document non trouvé.", 'error')
        return redirect(url_for('admin_documents_perdus'))
    
    # Obtenir les infos du déclarant
    declarant = get_user_by_id(document.get('user_id', ''))
    
    return render_template('admin/document_detail.html', user=user, document=document, declarant=declarant)

@app.route('/admin/alertes')
def admin_alertes():
    """Affiche les alertes créées et les correspondances trouvées"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    db = charger_db()
    
    # Récupérer toutes les alertes
    alertes = db.get('alerts', [])
    documents = db.get('documents', [])
    
    # Enrichir les alertes avec correspondances
    alertes_enrichies = []
    for alerte in alertes:
        alerte_enrichie = alerte.copy()
        
        # Trouver les documents correspondants
        correspondances = []
        for doc in documents:
            # Vérifier si le document correspond à l'alerte
            if (doc.get('type_piece', '').lower() == alerte.get('type_piece', '').lower() and
                doc.get('nom', '').lower() == alerte.get('nom', '').lower() and
                doc.get('prenom', '').lower() == alerte.get('prenom', '').lower()):
                correspondances.append(doc)
        
        alerte_enrichie['nombre_correspondances'] = len(correspondances)
        alerte_enrichie['correspondances'] = correspondances
        alertes_enrichies.append(alerte_enrichie)
    
    return render_template('admin/alertes.html', user=user, alertes=alertes_enrichies)

@app.route('/admin/alertes/<alerte_id>')
def admin_alerte_detail(alerte_id):
    """Affiche les détails d'une alerte et ses correspondances"""
    if not session.get('is_admin'):
        flash("Accès refusé.", 'error')
        return redirect(url_for('admin_login'))
    
    user = get_user_by_id(session.get('user_id'))
    db = charger_db()
    
    # Trouver l'alerte
    alerte = None
    for a in db.get('alerts', []):
        if a.get('_id') == alerte_id:
            alerte = a
            break
    
    if not alerte:
        flash("Alerte non trouvée.", 'error')
        return redirect(url_for('admin_alertes'))
    
    # Trouver les correspondances
    documents = db.get('documents', [])
    correspondances = []
    for doc in documents:
        if (doc.get('type_piece', '').lower() == alerte.get('type_piece', '').lower() and
            doc.get('nom', '').lower() == alerte.get('nom', '').lower() and
            doc.get('prenom', '').lower() == alerte.get('prenom', '').lower()):
            
            # Ajouter infos do declarante
            declarant = get_user_by_id(doc.get('user_id', ''))
            doc_enrichido = doc.copy()
            doc_enrichido['declarant_email'] = declarant.get('email', 'N/A') if declarant else 'N/A'
            doc_enrichido['declarant_telephone'] = declarant.get('telephone', 'N/A') if declarant else 'N/A'
            correspondances.append(doc_enrichido)
    
    return render_template('admin/alerte_detail.html', user=user, alerte=alerte, correspondances=correspondances)

@app.route('/admin/logout')
def admin_logout():
    """Déconnexion de l'admin"""
    session.clear()
    flash("Vous êtes déconnecté de l'administration.", 'success')
    return redirect(url_for('home'))

# ============================================================
# Gestion des erreurs
# ============================================================

@app.errorhandler(404)
def page_not_found(error):
    """Gère les pages non trouvées"""
    return render_template('error.html', error_code=404, error_message="Page non trouvée"), 404

@app.errorhandler(500)
def internal_error(error):
    """Gère les erreurs serveur"""
    return render_template('error.html', error_code=500, error_message="Erreur serveur interne"), 500

# ============================================================
# Point d'entrée
# ============================================================

if __name__ == '__main__':
    # Initialiser l'administrateur par défaut
    init_default_admin()
    
    print("=" * 60)
    print("Samapiece - Plateforme de récupération de documents")
    print("=" * 60)
    print("L'application démarre sur http://localhost:5000")
    print("Appuyez sur Ctrl+C pour arrêter le serveur")
    print("=" * 60)
    
    app.run(debug=True, host='localhost', port=5000)
