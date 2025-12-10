# -*- coding: utf-8 -*-
"""
Samapiece - Application Flask avec PostgreSQL
Plateforme de récupération de documents officiels perdus
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
import uuid
import random
import string

# ============================================================
# Configuration de l'application Flask
# ============================================================

app = Flask(__name__)
app.secret_key = 'votre_cle_secrete_ici_changez_moi'

# Configuration du dossier d'uploads
UPLOAD_FOLDER = os.path.join(os.path.dirname(__file__), 'uploads')
ALLOWED_EXTENSIONS = {'jpg', 'jpeg', 'png', 'gif'}

if not os.path.exists(UPLOAD_FOLDER):
    os.makedirs(UPLOAD_FOLDER)

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024

# Configuration PostgreSQL
DB_HOST = os.environ.get('DB_HOST', 'localhost')
DB_USER = os.environ.get('DB_USER', 'postgres')
DB_PASS = os.environ.get('DB_PASS', 'postgres')
DB_NAME = os.environ.get('DB_NAME', 'samapiece')
DB_PORT = os.environ.get('DB_PORT', '5432')

app.config['SQLALCHEMY_DATABASE_URI'] = f'postgresql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

# Initialiser SQLAlchemy
from database import db, User, Document, init_db
db.init_app(app)

# Configuration SMTP
app.config['SMTP_SERVER'] = os.environ.get('SMTP_SERVER', 'smtp.gmail.com')
app.config['SMTP_PORT'] = int(os.environ.get('SMTP_PORT', 587))
app.config['SMTP_USERNAME'] = os.environ.get('SMTP_USERNAME', '')
app.config['SMTP_PASSWORD'] = os.environ.get('SMTP_PASSWORD', '')
app.config['EMAIL_FROM'] = os.environ.get('EMAIL_FROM', app.config['SMTP_USERNAME'])

# Serializer pour tokens
serializer = URLSafeTimedSerializer(app.secret_key)

# ============================================================
# Fonctions utilitaires
# ============================================================

def allowed_file(filename):
    """Vérifie si le fichier est autorisé"""
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS

def generate_recovery_code():
    """Génère un code de récupération unique"""
    code_length = 8
    characters = string.ascii_uppercase + string.digits
    return ''.join(random.choice(characters) for _ in range(code_length))

def get_user_by_id(user_id):
    """Récupère un utilisateur par ID"""
    try:
        user = User.query.filter_by(id=uuid.UUID(user_id)).first()
        return user.to_dict() if user else None
    except:
        return None

def find_user_by_email(email):
    """Trouve un utilisateur par email"""
    user = User.query.filter_by(email=email).first()
    return user.to_dict() if user else None

def find_user_by_telephone(telephone):
    """Trouve un utilisateur par téléphone"""
    user = User.query.filter_by(telephone=telephone).first()
    return user.to_dict() if user else None

def verify_user_credentials(email_or_phone, password):
    """Vérifie les identifiants d'un utilisateur"""
    user = User.query.filter((User.email == email_or_phone) | (User.telephone == email_or_phone)).first()
    
    if not user:
        return None
    
    if not user.password_hash or not check_password_hash(user.password_hash, password):
        return None
    
    if user.is_verified != 'true':
        return None
    
    return user.to_dict()

def send_confirmation_email(to_email, token, fullname):
    """Envoie un email de confirmation"""
    smtp_server = app.config.get('SMTP_SERVER')
    smtp_port = int(app.config.get('SMTP_PORT', 587))
    smtp_user = app.config.get('SMTP_USERNAME')
    smtp_pass = app.config.get('SMTP_PASSWORD')
    email_from = app.config.get('EMAIL_FROM')

    confirm_url = url_for('confirm_email', token=token, _external=True)
    subject = 'Confirmation d\'email - Samapiece'
    html = f"""
    <html>
        <body style='font-family: Arial, sans-serif; color: #2c3e50;'>
            <div style='max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#e67e22'>Bienvenue sur Samapiece!</h2>
                <p>Bonjour {fullname},</p>
                <p>Merci de vous être inscrit sur Samapiece. Cliquez sur le lien ci-dessous pour confirmer votre adresse email:</p>
                <p style='text-align:center;margin:30px 0;'>
                    <a href='{confirm_url}' style='display:inline-block;padding:12px 24px;background:#e67e22;color:#fff;border-radius:6px;text-decoration:none;font-weight:700;'>Confirmer mon email</a>
                </p>
                <p>Ce lien expire dans 24 heures.</p>
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
    """Envoie un email de réinitialisation de mot de passe"""
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
            <div style='max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#e67e22'>Réinitialisation du mot de passe</h2>
                <p>Bonjour {fullname},</p>
                <p>Cliquez ci-dessous pour réinitialiser votre mot de passe (lien valide 1 heure):</p>
                <p style='text-align:center;margin:30px 0;'>
                    <a href='{reset_url}' style='display:inline-block;padding:12px 24px;background:#e67e22;color:#fff;border-radius:6px;text-decoration:none;font-weight:700;'>Réinitialiser</a>
                </p>
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

def find_documents(query):
    """Recherche des documents"""
    results = []
    documents = Document.query.filter(
        Document.nom.ilike(f"%{query.get('nom', '')}%"),
        Document.prenom.ilike(f"%{query.get('prenom', '')}%"),
        Document.lieu_naissance.ilike(f"%{query.get('lieu_naissance', '')}%")
    ).all()
    
    for doc in documents:
        if query.get('date_naissance') and doc.date_naissance != query.get('date_naissance'):
            continue
        results.append(doc.to_dict())
    
    return results

def get_user_documents(user_id):
    """Récupère les documents d'un utilisateur"""
    documents = Document.query.filter_by(user_id=uuid.UUID(user_id)).all()
    return [doc.to_dict() for doc in documents]

def document_exists(type_piece, nom, prenom, date_naissance, lieu_naissance):
    """Vérifie si un document existe déjà"""
    doc = Document.query.filter_by(
        type_piece=type_piece,
        nom=nom,
        prenom=prenom,
        date_naissance=date_naissance,
        lieu_naissance=lieu_naissance
    ).first()
    return doc is not None

# ============================================================
# Routes
# ============================================================

@app.route('/')
def home():
    """Page d'accueil"""
    return render_template('home.html')

@app.route('/register', methods=['GET', 'POST'])
def register():
    """Page de connexion/inscription"""
    if request.method == 'GET':
        return render_template('register.html')
    
    action = request.form.get('action', 'login')
    
    if action == 'login':
        login_identifier = request.form.get('login_identifier', '').strip()
        password = request.form.get('password', '').strip()
        
        if not login_identifier or not password:
            flash('Email/Téléphone et mot de passe requis.', 'error')
            return render_template('register.html')
        
        user = verify_user_credentials(login_identifier, password)
        if user:
            session['user_id'] = str(user['_id'])
            session['user_name'] = f"{user.get('prenom', '')} {user.get('nom', '')}"
            flash(f"Bienvenue {session['user_name']}!", 'success')
            return redirect(url_for('dashboard'))
        else:
            flash('Identifiants incorrects ou compte non vérifié.', 'error')
            return render_template('register.html')
    
    elif action == 'register':
        nom = request.form.get('nom', '').strip()
        prenom = request.form.get('prenom', '').strip()
        email = request.form.get('email', '').strip().lower()
        telephone = request.form.get('telephone', '').strip()
        password = request.form.get('new_password', '').strip()
        confirm_password = request.form.get('confirm_password', '').strip()
        
        errors = []
        if not all([nom, prenom, email, password, confirm_password]):
            errors.append("Tous les champs sont obligatoires.")
        if len(password) < 6:
            errors.append("Le mot de passe doit contenir au moins 6 caractères.")
        if password != confirm_password:
            errors.append("Les mots de passe ne correspondent pas.")
        
        existing_user = User.query.filter((User.email == email) | (User.telephone == telephone)).first()
        if existing_user:
            errors.append("Cet email ou numéro de téléphone est déjà utilisé.")
        
        if errors:
            for error in errors:
                flash(error, 'error')
            return render_template('register.html')
        
        # Créer l'utilisateur
        user = User(
            email=email,
            telephone=telephone,
            password_hash=generate_password_hash(password),
            is_verified='false',
            data={'nom': nom, 'prenom': prenom, 'date_creation': datetime.utcnow().isoformat()}
        )
        
        db.session.add(user)
        db.session.commit()
        
        # Envoyer email de confirmation
        token = serializer.dumps(email, salt='email-confirm')
        send_confirmation_email(email, token, f"{prenom} {nom}")
        
        flash('Inscription réussie! Vérifiez votre email pour confirmer votre compte.', 'success')
        return render_template('register.html')
    
    return render_template('register.html')

@app.route('/confirm/<token>')
def confirm_email(token):
    """Confirme l'adresse email"""
    try:
        email = serializer.loads(token, salt='email-confirm', max_age=24*3600)
    except SignatureExpired:
        flash('Lien de confirmation expiré.', 'error')
        return redirect(url_for('register'))
    except BadSignature:
        flash('Lien de confirmation invalide.', 'error')
        return redirect(url_for('register'))
    
    user = User.query.filter_by(email=email).first()
    if user:
        user.is_verified = 'true'
        db.session.commit()
        flash('Email confirmé! Vous pouvez maintenant vous connecter.', 'success')
    
    return redirect(url_for('register'))

@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():
    """Demande de réinitialisation de mot de passe"""
    if request.method == 'GET':
        return render_template('forgot_password.html')
    
    email = request.form.get('email', '').strip()
    if not email:
        flash('Veuillez entrer votre adresse email.', 'error')
        return render_template('forgot_password.html')
    
    user = User.query.filter_by(email=email).first()
    if not user:
        flash('Si un compte existe pour cet email, un message a été envoyé.', 'success')
        return render_template('forgot_password.html')
    
    user_data = user.to_dict()
    token = serializer.dumps(email, salt='password-reset')
    sent = send_reset_email(email, token, f"{user_data.get('prenom', '')} {user_data.get('nom', '')}")
    
    if not sent:
        flash('Impossible d\'envoyer l\'email. Vérifiez la configuration SMTP.', 'error')
        return render_template('forgot_password.html')
    
    flash('Si un compte existe pour cet email, un message a été envoyé.', 'success')
    return render_template('forgot_password.html')

@app.route('/reset/<token>', methods=['GET', 'POST'])
def reset_password(token):
    """Page de réinitialisation de mot de passe"""
    try:
        email = serializer.loads(token, salt='password-reset', max_age=3600)
    except SignatureExpired:
        flash('Lien de réinitialisation expiré.', 'error')
        return redirect(url_for('forgot_password'))
    except BadSignature:
        flash('Lien invalide.', 'error')
        return redirect(url_for('forgot_password'))
    
    if request.method == 'GET':
        return render_template('reset_password.html')
    
    password = request.form.get('password', '').strip()
    confirm_password = request.form.get('confirm_password', '').strip()
    
    if not password or not confirm_password:
        flash('Les deux champs sont obligatoires.', 'error')
        return render_template('reset_password.html')
    
    if password != confirm_password:
        flash('Les mots de passe ne correspondent pas.', 'error')
        return render_template('reset_password.html')
    
    user = User.query.filter_by(email=email).first()
    if user:
        user.password_hash = generate_password_hash(password)
        db.session.commit()
        flash('Mot de passe réinitialisé avec succès!', 'success')
        return redirect(url_for('register'))
    
    flash('Erreur lors de la réinitialisation.', 'error')
    return redirect(url_for('forgot_password'))

@app.route('/declare', methods=['GET', 'POST'])
def declare():
    """Déclaration d'un document trouvé"""
    if 'user_id' not in session:
        flash("Vous devez être connecté pour déclarer un document.", 'warning')
        return redirect(url_for('register'))
    
    types_documents = [
        'Carte d\'identité', 'Passeport', 'Permis de conduire', 'Permis de séjour',
        'Titre de séjour', 'Certificat de naissance', 'Livret de famille', 'Autre'
    ]
    
    if request.method == 'GET':
        current_user = get_user_by_id(session['user_id'])
        return render_template('declare.html', types_documents=types_documents, current_user=current_user)
    
    type_piece = request.form.get('type_piece', '').strip()
    nom = request.form.get('nom', '').strip()
    prenom = request.form.get('prenom', '').strip()
    date_naissance = request.form.get('date_naissance', '').strip()
    lieu_naissance = request.form.get('lieu_naissance', '').strip()
    
    errors = []
    if not all([type_piece, nom, prenom, date_naissance, lieu_naissance]):
        errors.append("Tous les champs sont obligatoires.")
    
    if not errors and document_exists(type_piece, nom, prenom, date_naissance, lieu_naissance):
        errors.append("Ce document a déjà été déclaré.")
    
    if 'photo_piece' not in request.files:
        errors.append("Veuillez télécharger une image.")
    else:
        file = request.files['photo_piece']
        if not allowed_file(file.filename):
            errors.append("Format de fichier non autorisé.")
    
    if errors:
        for error in errors:
            flash(error, 'error')
        current_user = get_user_by_id(session['user_id'])
        return render_template('declare.html', types_documents=types_documents, current_user=current_user)
    
    # Créer le document
    file = request.files['photo_piece']
    doc_id = str(uuid.uuid4())
    file_extension = file.filename.rsplit('.', 1)[1].lower()
    filename = secure_filename(f"{doc_id}.{file_extension}")
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(filepath)
    
    document = Document(
        id=uuid.UUID(doc_id),
        user_id=uuid.UUID(session['user_id']),
        type_piece=type_piece,
        nom=nom,
        prenom=prenom,
        date_naissance=date_naissance,
        lieu_naissance=lieu_naissance,
        photo_path=f'uploads/{filename}',
        recovery_code=generate_recovery_code()
    )
    
    db.session.add(document)
    db.session.commit()
    
    return redirect(url_for('declaration_success'))

@app.route('/declaration-success')
def declaration_success():
    """Page de succès après déclaration"""
    return render_template('success.html', page_type='declaration')

@app.route('/search', methods=['GET', 'POST'])
def search():
    """Recherche d'un document"""
    if request.method == 'GET':
        return render_template('search.html')
    
    query = {
        'nom': request.form.get('nom', '').strip(),
        'prenom': request.form.get('prenom', '').strip(),
        'date_naissance': request.form.get('date_naissance', '').strip(),
        'lieu_naissance': request.form.get('lieu_naissance', '').strip()
    }
    
    results = find_documents(query)
    return render_template('results.html', results=results, query_count=len(results))

@app.route('/uploads/<filename>')
def uploaded_file(filename):
    """Serve uploaded files"""
    return redirect(url_for('static', filename=f'uploads/{filename}'))

@app.route('/dashboard')
def dashboard():
    """Tableau de bord utilisateur"""
    if 'user_id' not in session:
        flash("Vous devez être connecté.", 'warning')
        return redirect(url_for('register'))
    
    user = get_user_by_id(session['user_id'])
    documents = get_user_documents(session['user_id'])
    
    return render_template('dashboard.html', user=user, documents=documents)

@app.route('/profile')
def profile():
    """Profil utilisateur"""
    if 'user_id' not in session:
        return redirect(url_for('register'))
    
    user = get_user_by_id(session['user_id'])
    return render_template('profile.html', user=user)

@app.route('/logout')
def logout():
    """Déconnexion"""
    session.clear()
    flash("Vous avez été déconnecté.", 'success')
    return redirect(url_for('home'))

@app.route('/recover_document', methods=['POST'])
def recover_document():
    """Récupérer un document"""
    if 'user_id' not in session:
        flash("Veuillez vous connecter.", 'error')
        return redirect(url_for('register'))
    
    recovery_code = request.form.get('recovery_code', '').strip().upper()
    
    if not recovery_code:
        flash("Veuillez entrer un code de récupération.", 'error')
        return redirect(url_for('profile'))
    
    document = Document.query.filter_by(recovery_code=recovery_code).first()
    
    if not document:
        flash("Code de récupération invalide.", 'error')
        return redirect(url_for('profile'))
    
    owner = get_user_by_id(str(document.user_id))
    
    flash(f"Document trouvé! Contactez {owner.get('nom')} {owner.get('prenom')} pour le récupérer.", 'success')
    return redirect(url_for('profile'))

# ============================================================
# Erreurs
# ============================================================

@app.errorhandler(404)
def not_found(e):
    return render_template('error.html', error_code=404, message="Page non trouvée"), 404

@app.errorhandler(500)
def server_error(e):
    return render_template('error.html', error_code=500, message="Erreur serveur"), 500

# ============================================================
# Initialisation
# ============================================================

if __name__ == '__main__':
    with app.app_context():
        init_db(app)
    app.run(debug=True)
