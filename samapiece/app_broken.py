# -*- coding: utf-8 -*-
"""
Samapiece - Application Flask pour la récupération de documents officiels perdus
Une plateforme permettant de déclarer des documents trouvés et de rechercher des documents perdus.
"""

import os
import json
from datetime import datetime
from werkzeug.utils import secure_filename
from werkzeug.security import generate_password_hash, check_password_hash
from flask import Flask, render_template, request, redirect, url_for, session, flash
import smtplib
import ssl
from email.mime.text import MIMEText
from itsdangerous import URLSafeTimedSerializer, SignatureExpired, BadSignature
from bson.objectid import ObjectId

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

# Structure de données
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
    # Chercher par email ou téléphone
    user = find_user_by_email(identifier) or find_user_by_telephone(identifier)
    
    if not user:
        return None, False
    
    # Vérifier le mot de passe
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

    # Construire le message
    msg = MIMEText(html, 'html', 'utf-8')
    msg['Subject'] = subject
    msg['From'] = email_from
    msg['To'] = to_email

    try:
        # Utiliser TLS si port 587, sinon SSL si 465
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
    smtp_port = app.config.get('SMTP_PORT')
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

def add_document(doc_data):
    """Ajoute un nouveau document"""
    db = load_db()
    doc_id = str(ObjectId())
    doc_data['_id'] = doc_id
    db['documents'].append(doc_data)
    save_db(db)
    return doc_id

def find_documents(query):
    """Recherche des documents selon les critères"""
    db = load_db()
    results = []
    
    for doc in db.get('documents', []):
        match = True
        for key, value in query.items():
            if key not in doc or doc[key] != value:
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

    # Déterminer l'action: connexion ou création de compte
    action = request.form.get('action', 'login').strip()

    if action == 'login':
        # ============ ACTION: CONNEXION ============
        login_identifier = request.form.get('login_identifier', '').strip()
        password = request.form.get('password', '').strip()

        if not login_identifier or not password:
            flash("Veuillez entrer votre email/téléphone et votre mot de passe.", 'error')
            return render_template('register.html')

        # Vérifier les identifiants
        user, is_valid = verify_user_credentials(login_identifier, password)

        if not user or not is_valid:
            flash("Email/téléphone ou mot de passe incorrect.", 'error')
            return render_template('register.html')

        # Vérifier que le compte est confirmé
        if not user.get('is_verified', False):
            flash("Votre adresse email n'a pas été confirmée. Vérifiez votre boîte mail.", 'warning')
            return render_template('register.html')

        # Connexion réussie
        session['user_id'] = user['_id']
        session['user_name'] = f"{user['nom']} {user['prenom']}"

        flash(f"Connecté en tant que {user['nom']} {user['prenom']}!", 'success')
        return redirect(url_for('dashboard'))

    else:
        # ============ ACTION: CRÉER UN COMPTE ============
        nom = request.form.get('nom', '').strip()
        prenom = request.form.get('prenom', '').strip()
        email = request.form.get('email', '').strip()
        telephone = request.form.get('telephone', '').strip()
        new_password = request.form.get('new_password', '').strip()
        confirm_password = request.form.get('confirm_password', '').strip()

        # Validation
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

        # Vérifier les doublons
        if email and find_user_by_email(email):
            errors.append("Cet email est déjà utilisé.")
        if telephone and find_user_by_telephone(telephone):
            errors.append("Ce numéro de téléphone est déjà utilisé.")

        if errors:
            for error in errors:
                flash(error, 'error')
            return render_template('register.html')

        # Créer le nouvel utilisateur avec mot de passe hashé (non vérifié pour l'instant)
        new_user = {
            'nom': nom,
            'prenom': prenom,
            'email': email,
            'telephone': telephone if telephone else None,
            'password_hash': generate_password_hash(new_password),
            'date_creation': datetime.utcnow().isoformat(),
            'is_verified': False
        }

        # Ajouter l'utilisateur à la DB
        user_id = add_user(new_user)

        # Générer token de confirmation et envoyer l'email
        token = serializer.dumps(email, salt='email-confirm')
        sent = send_confirmation_email(email, token, f"{nom} {prenom}")

        if not sent:
            # Suppression de l'utilisateur créé en cas d'échec d'envoi
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
        email = serializer.loads(token, salt='email-confirm', max_age=60*60*24)  # 24h
    except SignatureExpired:
        flash("Le lien de confirmation a expiré. Veuillez vous réinscrire.", 'error')
        return redirect(url_for('register'))
    except BadSignature:
        flash("Lien de confirmation invalide.", 'error')
        return redirect(url_for('register'))

    # Trouver l'utilisateur et marquer comme vérifié
    user = find_user_by_email(email)
    if not user:
        flash("Utilisateur introuvable pour cette adresse.", 'error')
        return redirect(url_for('register'))

    # Mettre à jour la DB
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
        # Optionnel: auto-login
        session['user_id'] = user.get('_id')
        session['user_name'] = f"{user.get('nom')} {user.get('prenom')}"
        return redirect(url_for('dashboard'))

    flash("Impossible de vérifier votre compte.", 'error')
    return redirect(url_for('register'))


@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():
    """Demande de réinitialisation du mot de passe (envoie email)
    L'email est envoyé même si le compte n'existe pas (message neutre pour sécurité).
    """
    if request.method == 'GET':
        return render_template('forgot_password.html')

    email = request.form.get('email', '').strip()
    if not email:
        flash('Veuillez entrer votre adresse email.', 'error')
        return render_template('forgot_password.html')

    user = find_user_by_email(email)
    if not user:
        # Ne pas divulguer l'existence du compte
        flash('Si un compte existe pour cet email, un message a été envoyé.', 'success')
        return render_template('forgot_password.html')

    # Générer token (valide 1 heure)
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
        email = serializer.loads(token, salt='password-reset', max_age=60*60)  # 1 heure
    except SignatureExpired:
        flash('Le lien de réinitialisation a expiré. Demandez-en un nouveau.', 'error')
        return redirect(url_for('forgot_password'))
    except BadSignature:
        flash('Lien de réinitialisation invalide.', 'error')
        return redirect(url_for('forgot_password'))

    if request.method == 'GET':
        return render_template('reset_password.html', token=token)

    # POST -> appliquer le nouveau mot de passe
    new_password = request.form.get('new_password', '').strip()
    confirm_password = request.form.get('confirm_password', '').strip()

    if not new_password or len(new_password) < 6:
        flash('Le mot de passe doit contenir au moins 6 caractères.', 'error')
        return render_template('reset_password.html', token=token)
    if new_password != confirm_password:
        flash('Les mots de passe ne correspondent pas.', 'error')
        return render_template('reset_password.html', token=token)

    # Mettre à jour le mot de passe pour l'utilisateur
    db = load_db()
    updated = False
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            u['password_hash'] = generate_password_hash(new_password)
            # marquer vérifié si ce n'était pas le cas
            u['is_verified'] = True
            updated = True
            break

    if updated:
        save_db(db)
        flash('Mot de passe mis à jour avec succès. Vous êtes connecté.', 'success')
        # Auto-login
        user = find_user_by_email(email)
        session['user_id'] = user.get('_id')
        session['user_name'] = f"{user.get('nom')} {user.get('prenom')}"
        return redirect(url_for('dashboard'))

    flash('Impossible de trouver le compte.', 'error')
    return redirect(url_for('forgot_password'))


@app.route('/declare', methods=['GET', 'POST'])
def declare():
    """Route de déclaration - déclare un document trouvé"""
    
    # Vérifier que l'utilisateur est connecté
    if 'user_id' not in session:
        flash("✋ Pour déclarer un document, vous devez d'abord créer un compte ou vous connecter.", 'warning')
        return redirect(url_for('register'))
    
    types_documents = [
        'Carte d\'identité',
        'Passeport',
        'Permis de conduire',
        'Permis de séjour',
        'Titre de séjour',
        'Certificat de naissance',
        'Livret de famille',
        'Autre'
    ]
    
    if request.method == 'GET':
        # Obtenir l'utilisateur connecté
        current_user = get_user_by_id(session['user_id'])
        return render_template('declare.html', 
                             types_documents=types_documents,
                             current_user=current_user)
    
    # Traitement du POST
    type_piece = request.form.get('type_piece', '').strip()
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    telephone_declarant = request.form.get('telephone_declarant', '').strip()
    country_code = request.form.get('country_code', '+221').strip()
    
    # Combiner le code du pays et le numéro de téléphone
    full_phone = f"{country_code} {telephone_declarant}"
    
    # Validation
    errors = []
    
    if not type_piece:
        errors.append("Le type de document est obligatoire.")
    if not nom:
        errors.append("Le nom est obligatoire.")
    if not prenom:
        errors.append("Le prénom est obligatoire.")
    if not date_naissance:
        errors.append("La date de naissance est obligatoire.")
    if not lieu_naissance:
        errors.append("Le lieu de naissance est obligatoire.")
    if not telephone_declarant:
        errors.append("Le téléphone du déclarant est obligatoire.")
    
    # Vérifier que le document n'a pas déjà été déclaré
    if not errors and document_exists(type_piece, nom, prenom, date_naissance, lieu_naissance):
        errors.append("Ce document a déjà été déclaré dans le système. Vérifiez si le document correspondant existe dans la base de données.")
    
    # Vérifier le fichier image
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
    
    # Utiliser l'ID de l'utilisateur connecté
    user_obj_id = session['user_id']
    if not get_user_by_id(user_obj_id):
        flash("Votre compte n'existe plus.", 'error')
        session.clear()
        return redirect(url_for('register'))
    
    # Traiter le fichier image
    file = request.files['photo_piece']
    
    # Générer un ID unique pour le document AVANT de sauvegarder le fichier
    doc_id = str(ObjectId())
    file_extension = file.filename.rsplit('.', 1)[1].lower()
    filename = secure_filename(f"{doc_id}.{file_extension}")
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(filepath)
    
    # Sauvegarder le document avec l'ID généré
    new_document = {
        '_id': doc_id,  # Ajouter explicitement l'ID généré
        'type_piece': type_piece,
        'nom': nom,
        'prenom': prenom,
        'date_naissance': date_naissance,
        'lieu_naissance': lieu_naissance,
        'telephone_declarant': full_phone,
        'photo_path': f'uploads/{filename}',  # Chemin basé sur l'ID unique
        'user_id': user_obj_id,
        'date_declaration': datetime.utcnow().isoformat(),
        'recovery_code': generate_recovery_code()
    }
    
    # Ajouter directement à la BD sans laisser add_document générer un nouvel ID
    db = load_db()
    db['documents'].append(new_document)
    save_db(db)
    
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
    
    # Traitement du POST
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    
    # Validation: au moins un champ doit être rempli
    if not nom and not prenom and not date_naissance and not lieu_naissance:
        flash("Veuillez remplir au moins un champ de recherche.", 'error')
        return render_template('search.html')
    
    # Construire la requête
    query = {}
    if nom:
        query['nom'] = nom
    if prenom:
        query['prenom'] = prenom
    if date_naissance:
        query['date_naissance'] = date_naissance
    if lieu_naissance:
        query['lieu_naissance'] = lieu_naissance
    
    # Rechercher les documents correspondants
    results = find_documents(query)
    
    return render_template('results.html', results=results, query_count=len(results))


@app.route('/uploads/<filename>')
def uploaded_file(filename):
    """Serve les fichiers uploadés"""
    return redirect(url_for('static', filename=f'uploads/{filename}'))


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
    
    # Récupérer les documents déclarés par cet utilisateur
    user_documents = get_user_documents(user_id)
    
    return render_template('dashboard.html', 
                         user=user, 
                         documents=user_documents,
                         document_count=len(user_documents))

    """Valide l'adresse email via token envoyé par email"""
    try:
        email = serializer.loads(token, salt='email-confirm', max_age=60*60*24)  # 24h
    except SignatureExpired:
        flash("Le lien de confirmation a expiré. Veuillez vous réinscrire.", 'error')
        return redirect(url_for('register'))
    except BadSignature:
        flash("Lien de confirmation invalide.", 'error')
        return redirect(url_for('register'))

    # Trouver l'utilisateur et marquer comme vérifié
    user = find_user_by_email(email)
    if not user:
        flash("Utilisateur introuvable pour cette adresse.", 'error')
        return redirect(url_for('register'))

    # Mettre à jour la DB
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
        # Optionnel: auto-login
        session['user_id'] = user.get('_id')
        session['user_name'] = f"{user.get('nom')} {user.get('prenom')}"
        return redirect(url_for('dashboard'))

    flash("Impossible de vérifier votre compte.", 'error')
    return redirect(url_for('register'))


@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():
    """Demande de réinitialisation du mot de passe (envoie email)
    L'email est envoyé même si le compte n'existe pas (message neutre pour sécurité).
    """
    if request.method == 'GET':
        return render_template('forgot_password.html')

    email = request.form.get('email', '').strip()
    if not email:
        flash('Veuillez entrer votre adresse email.', 'error')
        return render_template('forgot_password.html')

    user = find_user_by_email(email)
    if not user:
        # Ne pas divulguer l'existence du compte
        flash('Si un compte existe pour cet email, un message a été envoyé.', 'success')
        return render_template('forgot_password.html')

    # Générer token (valide 1 heure)
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
        email = serializer.loads(token, salt='password-reset', max_age=60*60)  # 1 heure
    except SignatureExpired:
        flash('Le lien de réinitialisation a expiré. Demandez-en un nouveau.', 'error')
        return redirect(url_for('forgot_password'))
    except BadSignature:
        flash('Lien de réinitialisation invalide.', 'error')
        return redirect(url_for('forgot_password'))

    if request.method == 'GET':
        return render_template('reset_password.html', token=token)

    # POST -> appliquer le nouveau mot de passe
    new_password = request.form.get('new_password', '').strip()
    confirm_password = request.form.get('confirm_password', '').strip()

    if not new_password or len(new_password) < 6:
        flash('Le mot de passe doit contenir au moins 6 caractères.', 'error')
        return render_template('reset_password.html', token=token)
    if new_password != confirm_password:
        flash('Les mots de passe ne correspondent pas.', 'error')
        return render_template('reset_password.html', token=token)

    # Mettre à jour le mot de passe pour l'utilisateur
    db = load_db()
    updated = False
    for u in db.get('users', []):
        if u.get('email', '').lower() == email.lower():
            u['password_hash'] = generate_password_hash(new_password)
            # marquer vérifié si ce n'était pas le cas
            u['is_verified'] = True
            updated = True
            break

    if updated:
        save_db(db)
        flash('Mot de passe mis à jour avec succès. Vous êtes connecté.', 'success')
        # Auto-login
        user = find_user_by_email(email)
        session['user_id'] = user.get('_id')
        session['user_name'] = f"{user.get('nom')} {user.get('prenom')}"
        return redirect(url_for('dashboard'))

    flash('Impossible de trouver le compte.', 'error')
    return redirect(url_for('forgot_password'))

def declare():
    """Route de déclaration - déclare un document trouvé"""
    
    # Vérifier que l'utilisateur est connecté
    if 'user_id' not in session:
        flash("✋ Pour déclarer un document, vous devez d'abord créer un compte ou vous connecter.", 'warning')
        return redirect(url_for('register'))
    
    types_documents = [
        'Carte d\'identité',
        'Passeport',
        'Permis de conduire',
        'Permis de séjour',
        'Titre de séjour',
        'Certificat de naissance',
        'Livret de famille',
        'Autre'
    ]
    
    if request.method == 'GET':
        # Obtenir l'utilisateur connecté
        current_user = get_user_by_id(session['user_id'])
        return render_template('declare.html', 
                             types_documents=types_documents,
                             current_user=current_user)
    
    # Traitement du POST
    type_piece = request.form.get('type_piece', '').strip()
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    telephone_declarant = request.form.get('telephone_declarant', '').strip()
    country_code = request.form.get('country_code', '+221').strip()
    
    # Combiner le code du pays et le numéro de téléphone
    full_phone = f"{country_code} {telephone_declarant}"
    
    # Validation
    errors = []
    
    if not type_piece:
        errors.append("Le type de document est obligatoire.")
    if not nom:
        errors.append("Le nom est obligatoire.")
    if not prenom:
        errors.append("Le prénom est obligatoire.")
    if not date_naissance:
        errors.append("La date de naissance est obligatoire.")
    if not lieu_naissance:
        errors.append("Le lieu de naissance est obligatoire.")
    if not telephone_declarant:
        errors.append("Le téléphone du déclarant est obligatoire.")
    
    # Vérifier que le document n'a pas déjà été déclaré
    if not errors and document_exists(type_piece, nom, prenom, date_naissance, lieu_naissance):
        errors.append("Ce document a déjà été déclaré dans le système. Vérifiez si le document correspondant existe dans la base de données.")
    
    # Vérifier le fichier image
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
    
    # Utiliser l'ID de l'utilisateur connecté
    user_obj_id = session['user_id']
    if not get_user_by_id(user_obj_id):
        flash("Votre compte n'existe plus.", 'error')
        session.clear()
        return redirect(url_for('register'))
    
    # Traiter le fichier image
    file = request.files['photo_piece']
    
    # Générer un ID unique pour le document AVANT de sauvegarder le fichier
    doc_id = str(ObjectId())
    file_extension = file.filename.rsplit('.', 1)[1].lower()
    filename = secure_filename(f"{doc_id}.{file_extension}")
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(filepath)
    
    # Sauvegarder le document avec l'ID généré
    new_document = {
        '_id': doc_id,  # Ajouter explicitement l'ID généré
        'type_piece': type_piece,
        'nom': nom,
        'prenom': prenom,
        'date_naissance': date_naissance,
        'lieu_naissance': lieu_naissance,
        'telephone_declarant': full_phone,
        'photo_path': f'uploads/{filename}',  # Chemin basé sur l'ID unique
        'user_id': user_obj_id,
        'date_declaration': datetime.utcnow().isoformat(),
        'recovery_code': generate_recovery_code()
    }
    
    # Ajouter directement à la BD sans laisser add_document générer un nouvel ID
    db = load_db()
    db['documents'].append(new_document)
    save_db(db)
    
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
    
    # Traitement du POST
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    
    # Validation: au moins un champ doit être rempli
    if not nom and not prenom and not date_naissance and not lieu_naissance:
        flash("Veuillez remplir au moins un champ de recherche.", 'error')
        return render_template('search.html')
    
    # Construire la requête
    query = {}
    if nom:
        query['nom'] = nom
    if prenom:
        query['prenom'] = prenom
    if date_naissance:
        query['date_naissance'] = date_naissance
    if lieu_naissance:
        query['lieu_naissance'] = lieu_naissance
    
    # Rechercher les documents correspondants
    results = find_documents(query)
    
    return render_template('results.html', results=results, query_count=len(results))

@app.route('/uploads/<filename>')
def uploaded_file(filename):
    """Serve les fichiers uploadés"""
    return redirect(url_for('static', filename=f'uploads/{filename}'))

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
    
    # Récupérer les documents déclarés par cet utilisateur
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
    
    # Récupérer les documents déclarés par cet utilisateur
    user_documents = get_user_documents(user_id)
    
    return render_template('profile.html', user=user, user_documents=user_documents)

@app.route('/logout')
def logout():
    """Déconnexion de l'utilisateur"""
    session.clear()
    flash("Vous êtes déconnecté.", 'success')
    return redirect(url_for('home'))

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
    
    # Chercher le document avec ce code de récupération
    db = load_db()
    found_document = None
    
    for doc in db.get('documents', []):
        if doc.get('recovery_code', '').upper() == recovery_code:
            found_document = doc
            break
    
    if not found_document:
        flash("Code de récupération invalide. Vérifiez le code et réessayez.", 'error')
        return redirect(url_for('profile'))
    
    # Récupérer les informations du propriétaire du document perdu
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
