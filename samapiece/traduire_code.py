#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script de traduction automatique du code de l'anglais/portugais vers le français
"""

import re

# Dictionnaire de traductions
TRADUCTIONS = {
    # Noms de fonctions
    'load_db': 'charger_db',
    'save_db': 'sauvegarder_db',
    'log_security_event': 'enregistrer_evenement_securite',
    'init_db': 'initialiser_db',
    'generate_recovery_code': 'generer_code_recuperation',
    'get_all_lost_items': 'obtenir_tous_objets_perdus',
    'verify_email': 'verifier_email',
    'send_email': 'envoyer_email',
    'allowed_file': 'fichier_autorise',
    'optimize_image': 'optimiser_image',
    'require_admin': 'exiger_admin',
    'require_login': 'exiger_connexion',
    
    # Noms de variables
    'DATABASE_FILE': 'FICHIER_BASE_DONNEES',
    'UPLOAD_FOLDER': 'DOSSIER_UPLOAD',
    'ALLOWED_EXTENSIONS': 'EXTENSIONS_AUTORISEES',
    'MAX_CONTENT_LENGTH': 'LONGUEUR_CONTENU_MAX',
    'SMTP_SERVER': 'SERVEUR_SMTP',
    'SMTP_PORT': 'PORT_SMTP',
    'SMTP_USERNAME': 'NOM_UTILISATEUR_SMTP',
    'SMTP_PASSWORD': 'MOT_PASSE_SMTP',
    'EMAIL_FROM': 'EMAIL_DE',
    
    # Commentaires communs
    'load database from JSON': 'charger la base de donnees depuis JSON',
    'save database to JSON': 'sauvegarder la base de donnees en JSON',
    'initialize database': 'initialiser la base de donnees',
    'get all users': 'obtenir tous les utilisateurs',
    'get all documents': 'obtenir tous les documents',
    'check password': 'verifier le mot de passe',
    'generate token': 'generer un jeton',
    'validate email': 'valider l\'email',
    'send email': 'envoyer un email',
    'optimize image': 'optimiser l\'image',
    'request context': 'contexte de la requete',
}

def traduire_fichier(chemin_entree, chemin_sortie=None):
    """Traduit le contenu du fichier"""
    if chemin_sortie is None:
        chemin_sortie = chemin_entree
    
    with open(chemin_entree, 'r', encoding='utf-8') as f:
        contenu = f.read()
    
    # Appliquer les traductions
    for original, traduction in TRADUCTIONS.items():
        # Traductions sensibles à la casse pour les variables/fonctions
        if original.isupper() or original[0].isupper():
            # Constantes et noms de classe
            contenu = re.sub(r'\b' + original + r'\b', traduction, contenu)
        else:
            # Fonctions et variables
            contenu = re.sub(r'\b' + original + r'\b(?=\s*\(|\s*=|\s*\.|\s*,)', traduction, contenu)
    
    with open(chemin_sortie, 'w', encoding='utf-8') as f:
        f.write(contenu)
    
    print(f"[OK] Fichier traduit: {chemin_sortie}")

if __name__ == '__main__':
    # Traduire app.py
    traduire_fichier('app.py')
