"""
Configuration et modèles de base de données PostgreSQL avec SQLAlchemy
"""
import os
import json
from datetime import datetime
from flask_sqlalchemy import SQLAlchemy
from sqlalchemy import Column, String, DateTime, JSON, Index
from sqlalchemy.dialects.postgresql import UUID
import uuid

db = SQLAlchemy()

class User(db.Model):
    """Modèle utilisateur avec schéma flexible (JSON)"""
    __tablename__ = 'users'
    
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    email = Column(String(255), unique=True, nullable=False, index=True)
    telephone = Column(String(20), nullable=True)
    password_hash = Column(String(255), nullable=True)
    is_verified = Column(String(5), nullable=False, default='false')  # 'true' ou 'false' en string
    data = Column(JSON, nullable=True)  # Données flexibles: nom, prenom, code_pays, date_creation
    created_at = Column(DateTime, default=datetime.utcnow)
    
    def __repr__(self):
        return f'<User {self.email}>'
    
    def to_dict(self):
        """Convertir en dictionnaire pour compatibilité avec le code existant"""
        result = {
            '_id': str(self.id),
            'email': self.email,
            'telephone': self.telephone,
            'password_hash': self.password_hash or '',
            'is_verified': self.is_verified == 'true',
        }
        if self.data:
            result.update(self.data)
        return result


class Document(db.Model):
    """Modèle document avec schéma flexible (JSON)"""
    __tablename__ = 'documents'
    
    id = Column(UUID(as_uuid=True), primary_key=True, default=uuid.uuid4)
    user_id = Column(UUID(as_uuid=True), nullable=False, index=True)
    type_piece = Column(String(100), nullable=False, index=True)
    nom = Column(String(100), nullable=False, index=True)
    prenom = Column(String(100), nullable=False, index=True)
    date_naissance = Column(String(10), nullable=False)  # Format: YYYY-MM-DD
    lieu_naissance = Column(String(100), nullable=False, index=True)
    photo_path = Column(String(255), nullable=True)
    recovery_code = Column(String(20), unique=True, nullable=True, index=True)
    data = Column(JSON, nullable=True)  # Données flexibles additionnelles
    created_at = Column(DateTime, default=datetime.utcnow)
    
    __table_args__ = (
        Index('idx_search_composite', 'type_piece', 'nom', 'prenom', 'date_naissance', 'lieu_naissance'),
    )
    
    def __repr__(self):
        return f'<Document {self.type_piece} - {self.nom} {self.prenom}>'
    
    def to_dict(self):
        """Convertir en dictionnaire pour compatibilité avec le code existant"""
        result = {
            '_id': str(self.id),
            'type_piece': self.type_piece,
            'nom': self.nom,
            'prenom': self.prenom,
            'date_naissance': self.date_naissance,
            'lieu_naissance': self.lieu_naissance,
            'photo_path': self.photo_path,
            'recovery_code': self.recovery_code,
            'user_id': str(self.user_id),
            'date_declaration': self.created_at.isoformat(),
        }
        if self.data:
            result.update(self.data)
        return result


def init_db(app):
    """Initialiser la base de données"""
    with app.app_context():
        db.create_all()
        print("✓ Base de données PostgreSQL initialisée")
