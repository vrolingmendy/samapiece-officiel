# Guide de développement - Samapiece

## Table des matières

1. [Architecture](#architecture)
2. [Structure des fichiers](#structure-des-fichiers)
3. [Conventions de codage](#conventions-de-codage)
4. [Classes principales](#classes-principales)
5. [Ajouter une nouvelle fonctionnalité](#ajouter-une-nouvelle-fonctionnalite)
6. [Déploiement](#deploiement)

## Architecture

### Vue d'ensemble de l'architecture

```
┌─────────────────────────────────────────────┐
│         Interface Utilisateur (HTML/CSS)    │
├─────────────────────────────────────────────┤
│         Routes Flask (app.py)               │
├─────────────────────────────────────────────┤
│  GestionnaireSécurité | Utilitaires         │
├─────────────────────────────────────────────┤
│    Base de données JSON (data.json)         │
└─────────────────────────────────────────────┘
```

### Flux de données

1. L'utilisateur interagit avec les templates HTML
2. Les formulaires sont traités par les routes Flask
3. Le gestionnaire de sécurité valide les données
4. Les données sont stockées/récupérées depuis JSON
5. La réponse est rendue et renvoyée à l'utilisateur

## Structure des fichiers

```
samapiece/
├── app.py                          # Application principale Flask
├── database.py                     # Gestion de la base de données (optionnel)
├── data.json                       # Base de données JSON
├── requirements.txt                # Dépendances Python
├── config.md                       # Documentation de configuration
├── README_FR.md                    # Documentation en français
├── GUIDE_DEVELOPPEMENT.md         # Ce fichier
├── static/
│   └── img/                        # Images statiques
├── templates/
│   ├── base.html                   # Template de base
│   ├── home.html                   # Page d'accueil
│   ├── login.html                  # Connexion utilisateur
│   ├── register.html               # Inscription
│   ├── dashboard.html              # Tableau de bord utilisateur
│   ├── documents.html              # Liste des documents
│   ├── search.html                 # Recherche
│   └── admin/
│       ├── admin_login.html        # Connexion admin
│       ├── admin_dashboard.html    # Tableau de bord admin
│       ├── users.html              # Gestion des utilisateurs
│       ├── documents_perdus.html   # Documents trouvés
│       ├── document_detail.html    # Détail document
│       ├── alertes.html            # Liste des alertes
│       └── alerte_detail.html      # Détail alerte
└── uploads/                        # Documents uploadés (ignoré par git)
```

## Conventions de codage

### Noms des fonctions en français

Toutes les fonctions doivent être nommées en français avec le format snake_case:

```python
# BON
def charger_db():
    """Charge la base de donnees"""
    pass

def valider_email(email):
    """Valide le format de l'email"""
    pass

# MAUVAIS
def load_database():
    pass

def check_email(mail):
    pass
```

### Noms des variables

Les variables locales utilisent le français:

```python
# BON
utilisateurs = charger_utilisateurs()
nombre_documents = len(documents)
est_admin = session.get('is_admin')

# MAUVAIS
users = load_users()
doc_count = get_document_count()
admin_flag = session['admin']
```

### Commentaires et docstrings

Les commentaires et docstrings doivent être en français:

```python
def enregistrer_evenement_securite(type_evenement, details):
    """
    Enregistre un événement de sécurité dans la base de données.
    
    Arguments:
        type_evenement (str): Type d'événement (CONNEXION_ECHOUEE, COMPTE_BLOQUE, etc.)
        details (dict): Détails additionnels de l'événement
    
    Returns:
        bool: True si enregistrement réussi, False sinon
    """
    pass
```

## Classes principales

### GestionnaireSécurité

```python
class GestionnaireSécurité:
    """Gère tous les aspects de sécurité de l'application"""
    
    MAX_TENTATIVES_CONNEXION = 5
    TEMPS_BLOCAGE_MINUTES = 30
    
    @staticmethod
    def obtenir_hash_client(request):
        """Obtient un hash unique pour le client"""
        pass
    
    @staticmethod
    def valider_force_mot_passe(mot_passe):
        """Valide la force du mot de passe"""
        pass
    
    @staticmethod
    def verifier_tentatives_connexion(identifiant, objet_requete):
        """Vérifie si l'utilisateur a dépassé le nombre de tentatives"""
        pass
```

**Utilisation:**
```python
@app.route('/admin/connexion', methods=['POST'])
def connexion_admin():
    email = request.form.get('email')
    mot_passe = request.form.get('mot_passe')
    
    # Vérifier les tentatives échouées
    peut_essayer, statut = GestionnaireSécurité.verifier_tentatives_connexion(email, request)
    
    if not peut_essayer:
        flash("Compte bloque. Essayez plus tard.", 'erreur')
        return redirect(url_for('connexion_admin'))
```

## Ajouter une nouvelle fonctionnalité

### Exemple: Ajouter une page d'export de documents

1. **Créer la route dans app.py**
```python
@app.route('/admin/exporter-documents')
@exiger_admin
def exporter_documents():
    """Exporte tous les documents au format CSV"""
    db = charger_db()
    documents = db.get('documents', [])
    
    # Créer le CSV
    csv_data = "Type,Nom,Prenom,Date\n"
    for doc in documents:
        csv_data += f"{doc['type_piece']},{doc['nom']},{doc['prenom']},{doc['date_declaration']}\n"
    
    return Response(csv_data, mimetype="text/csv", 
                   headers={"Content-Disposition": "attachment; filename=documents.csv"})
```

2. **Créer le template HTML**
```html
<!-- templates/admin/exporter_documents.html -->
{% extends "base.html" %}

{% block content %}
<h2>Exporter les documents</h2>
<a href="{{ url_for('exporter_documents') }}" class="btn">Telecharger en CSV</a>
{% endblock %}
```

3. **Ajouter le lien dans le menu**
```html
<!-- templates/admin/admin_dashboard.html -->
<a href="{{ url_for('exporter_documents') }}">Exporter</a>
```

## Déploiement

### En production avec Gunicorn

1. **Installer Gunicorn**
```bash
pip install gunicorn
```

2. **Lancer l'application**
```bash
gunicorn -w 4 -b 0.0.0.0:5000 app:app
```

### Avec un serveur Nginx

```nginx
server {
    listen 80;
    server_name votre_domaine.com;

    location / {
        proxy_pass http://127.0.0.1:5000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### Variables d'environnement de production

```bash
export FLASK_ENV=production
export FLASK_DEBUG=False
export SECRET_KEY=votre_cle_secrete_complexe_et_aleatoire
export SMTP_SERVER=smtp.gmail.com
export SMTP_USERNAME=votre_email
export SMTP_PASSWORD=mot_passe_application
```

## Maintenance

### Sauvegarder les données

```bash
# Créer une sauvegarde de data.json
cp data.json backups/data_$(date +%Y%m%d_%H%M%S).json
```

### Nettoyer les anciens fichiers

```bash
# Supprimer les images non utilisées du dossier uploads
find uploads/ -mtime +30 -delete
```

### Vérifier les logs

```bash
# Consulter les dernières lignes des journaux de sécurité
tail -n 50 data.json | grep "journaux_securite"
```

## Ressources utiles

- [Documentation Flask](https://flask.palletsprojects.com/)
- [Sécurité Flask](https://flask.palletsprojects.com/en/2.3.x/security/)
- [Werkzeug Security](https://werkzeug.palletsprojects.com/en/2.3.x/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

## Support et questions

Pour des questions sur le développement:
1. Vérifier la documentation existante
2. Consulter les issues GitHub
3. Créer une nouvelle issue si nécessaire

---

**Mis à jour:** 11 décembre 2025
**Responsable:** Vrolin Mendy
