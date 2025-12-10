"""
Script de migration JSON vers PostgreSQL
Exécuter ce script une seule fois pour migrer les données
"""
import json
import os
from database import db, User, Document, init_db
from flask import Flask
import uuid

# Créer une mini-app pour le contexte Flask
app = Flask(__name__)
DB_HOST = os.environ.get('DB_HOST', 'localhost')
DB_USER = os.environ.get('DB_USER', 'postgres')
DB_PASS = os.environ.get('DB_PASS', 'postgres')
DB_NAME = os.environ.get('DB_NAME', 'samapiece')
DB_PORT = os.environ.get('DB_PORT', '5432')

app.config['SQLALCHEMY_DATABASE_URI'] = f'postgresql://{DB_USER}:{DB_PASS}@{DB_HOST}:{DB_PORT}/{DB_NAME}'
db.init_app(app)

def migrate_from_json():
    """Migrer les données de JSON vers PostgreSQL"""
    json_file = os.path.join(os.path.dirname(__file__), 'data.json')
    
    if not os.path.exists(json_file):
        print("❌ Fichier data.json non trouvé")
        return
    
    with app.app_context():
        # Créer les tables
        init_db(app)
        
        # Charger les données JSON
        with open(json_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        # Migrer les utilisateurs
        print(f"Migrating {len(data.get('users', []))} users...")
        for user_data in data.get('users', []):
            existing = User.query.filter_by(email=user_data.get('email')).first()
            if not existing:
                user = User(
                    id=uuid.UUID(user_data.get('_id', str(uuid.uuid4()))) if user_data.get('_id') and len(user_data.get('_id')) == 24 else uuid.uuid4(),
                    email=user_data.get('email'),
                    telephone=user_data.get('telephone'),
                    password_hash=user_data.get('password_hash'),
                    is_verified='true' if user_data.get('is_verified', False) else 'false',
                    data={k: v for k, v in user_data.items() if k not in ['_id', 'email', 'telephone', 'password_hash', 'is_verified', 'date_creation']}
                )
                db.session.add(user)
                print(f"  ✓ User: {user_data.get('email')}")
        
        # Migrer les documents
        print(f"Migrating {len(data.get('documents', []))} documents...")
        for doc_data in data.get('documents', []):
            existing = Document.query.filter_by(id=uuid.UUID(doc_data.get('_id'))).first()
            if not existing:
                document = Document(
                    id=uuid.UUID(doc_data.get('_id', str(uuid.uuid4()))),
                    user_id=uuid.UUID(doc_data.get('user_id')),
                    type_piece=doc_data.get('type_piece'),
                    nom=doc_data.get('nom'),
                    prenom=doc_data.get('prenom'),
                    date_naissance=doc_data.get('date_naissance'),
                    lieu_naissance=doc_data.get('lieu_naissance'),
                    photo_path=doc_data.get('photo_path'),
                    recovery_code=doc_data.get('recovery_code'),
                    data={k: v for k, v in doc_data.items() if k not in ['_id', 'user_id', 'type_piece', 'nom', 'prenom', 'date_naissance', 'lieu_naissance', 'photo_path', 'recovery_code', 'date_declaration']}
                )
                db.session.add(document)
                print(f"  ✓ Document: {doc_data.get('type_piece')} - {doc_data.get('nom')} {doc_data.get('prenom')}")
        
        db.session.commit()
        print("\n✅ Migration terminée avec succès!")

if __name__ == '__main__':
    migrate_from_json()
