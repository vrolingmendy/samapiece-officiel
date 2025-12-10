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

# ============================================================
# Configuration de l'application Flask
# ============================================================

app = Flask(__name__)
app.secret_key = 'votre_cle_secrete_ici_changez_moi'  # À changer en production

# Configuration du dossier d'uploads
UPLOAD_FOLDER = os.path.join(os.path.dirname(__file__), 'uploads')
ALLOWED_EXTENSIONS = {'jpg', 'jpeg', 'png', 'gif'}

if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024  # Limite à 16 MB

# --- Email / SMTP configuration (à configurer via variables d'environnement) ---
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

DATABASE_FILE = os.path.join(os.path.dirname(__file__), 'data.json')

def init_db():
    """Initialise la base de données JSON si elle n'existe pas"""
    if not os.path.exists(DATABASE_FILE):
        data = {
            'users': [],
            'documents': []
        }
        with open(DATABASE_FILE, 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, indent=2, default=str)
    return load_db()

def load_db():
    """Charge les données depuis le fichier JSON"""
    try:
        with open(DATABASE_FILE, 'r', encoding='utf-8') as f:
            return json.load(f)
    except:
        return {'users': [], 'documents': []}

def save_db(data):
    """Sauvegarde les données dans le fichier JSON"""
    with open(DATABASE_FILE, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2, default=str)

# Initialiser la BD
db = init_db()
print("✓ Base de données JSON initialisée")

# ============================================================
# Fonctions utilitaires
# ============================================================

def allowed_file(filename):
    """Vérifie si le fichier uploadé est un type autorisé"""
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

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
        print(f"✓ Image optimisée: {file_path}")
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

def get_all_users():
    """Récupère la liste de tous les utilisateurs"""
    db = load_db()
    return db.get('users', [])

def get_user_by_id(user_id):
    """Récupère un utilisateur par son ID"""
    db = load_db()
    for user in db.get('users', []):
        if user.get('_id') == user_id:
            return user
    return None

def add_user(user_data):
    """Ajoute un nouvel utilisateur"""
    db = load_db()
    user_id = str(ObjectId())
    user_data['_id'] = user_id
    db['users'].append(user_data)
    save_db(db)
    return user_id

def find_user_by_email(email):
    """Trouve un utilisateur par email"""
    db = load_db()
    for user in db.get('users', []):
        if user.get('email', '').lower() == email.lower():
            return user
    return None

def find_user_by_telephone(telephone):
    """Trouve un utilisateur par numéro de téléphone"""
    db = load_db()
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
    db = load_db()
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
    db = load_db()
    user_docs = []
    for doc in db.get('documents', []):
        if doc.get('user_id') == user_id:
            user_docs.append(doc)
    return user_docs

def document_exists(type_piece, nom, prenom, date_naissance, lieu_naissance):
    """Vérifie si un document avec les mêmes infos existe déjà"""
    db = load_db()
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
    db = load_db()
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
    save_db(db)
    return alert

def get_matching_alerts(type_piece, nom, prenom, date_naissance, lieu_naissance):
    """Récupère les alertes qui correspondent à un nouveau document"""
    db = load_db()
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
            db = load_db()
            db['users'] = [u for u in db.get('users', []) if u.get('_id') != user_id]
            save_db(db)
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

    db = load_db()
    updated = False
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            u['is_verified'] = True
            updated = True
            break
    if updated:
        save_db(db)
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

    db = load_db()
    updated = False
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            u['password_hash'] = generate_password_hash(new_password)
            u['is_verified'] = True
            updated = True
            break

    if updated:
        save_db(db)
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
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(filepath)
    
    # Optimiser l'image
    optimize_image(filepath, max_width=1200, max_height=900, quality=85)
    
    db = load_db()
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
    
    save_db(db)
    
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
        return send_from_directory(app.config['UPLOAD_FOLDER'], filename, as_attachment=False)
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
    
    db = load_db()
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
    print("=" * 60)
    print("Samapiece - Plateforme de récupération de documents")
    print("=" * 60)
    print("L'application démarre sur http://localhost:5000")
    print("Appuyez sur Ctrl+C pour arrêter le serveur")
    print("=" * 60)
    
    app.run(debug=True, host='localhost', port=5000)
