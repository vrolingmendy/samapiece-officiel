# Configuration de Samapiece

## 📋 Résumé du Projet

**Samapiece** est une application web complète pour aider les utilisateurs à retrouver leurs documents officiels perdus.

---

## 📂 Fichiers du Projet

### Backend Python
- **app.py** (880 lignes)
  - Application Flask principale
  - Gestion des routes (accueil, enregistrement, déclaration, recherche)
  - Connexion MongoDB avec PyMongo
  - Gestion des uploads de fichiers
  - Gestion des sessions utilisateur

### Templates HTML/Jinja2 (8 fichiers)
- **base.html**: Template de base avec CSS intégré (responsive, minimaliste)
- **home.html**: Page d'accueil avec actions principales
- **register.html**: Formulaire d'enregistrement utilisateur
- **declare.html**: Formulaire de déclaration de document trouvé
- **search.html**: Formulaire de recherche de document perdu
- **results.html**: Affichage des résultats de recherche avec images
- **success.html**: Page de confirmation après actions
- **error.html**: Page d'erreur

### Configuration
- **requirements.txt**: Dépendances Python
- **README.md**: Documentation complète
- **QUICK_START.md**: Guide de démarrage rapide pour Windows
- **.gitignore**: Fichiers à ignorer dans Git

### Dossiers
- **templates/**: 8 fichiers HTML
- **uploads/**: Dossier pour les images uploadées

---

## 🔧 Dépendances (requirements.txt)

```
Flask==2.3.3              # Framework web Python
pymongo==4.6.0            # Driver MongoDB pour Python
werkzeug==2.3.7           # Utilitaires Flask (secure_filename, etc.)
python-dotenv==1.0.0      # Gestion des variables d'environnement
```

---

## 🗄️ Modèle MongoDB

### Collection: users
```javascript
{
  _id: ObjectId,           // ID unique MongoDB
  nom: String,             // Nom complet
  prenom: String,          // Prénom
  email: String | null,    // Email (optionnel)
  telephone: String | null,// Téléphone (optionnel)
  code_pays: String | null,// Code pays international (+33, +221, etc.)
  date_creation: Date      // Timestamp de création
}
```

### Collection: documents
```javascript
{
  _id: ObjectId,              // ID unique MongoDB
  type_piece: String,         // Type (CNI, Passeport, Permis, etc.)
  nom: String,                // Nom du propriétaire
  prenom: String,             // Prénom du propriétaire
  date_naissance: String,     // Date de naissance
  lieu_naissance: String,     // Lieu de naissance
  telephone_declarant: String,// Téléphone du déclarant
  photo_path: String,         // Chemin relatif de l'image (uploads/...)
  user_id: ObjectId,          // Référence à l'utilisateur déclarant
  date_declaration: Date      // Timestamp de déclaration
}
```

---

## 🛣️ Routes Flask

| Route | Méthode | Fonction | Template |
|-------|---------|----------|----------|
| `/` | GET | Accueil | home.html |
| `/register` | GET | Affiche formulaire | register.html |
| `/register` | POST | Crée utilisateur | → registration_success |
| `/declaration-success` | GET | Succès enregistrement | success.html |
| `/declare` | GET | Affiche formulaire | declare.html |
| `/declare` | POST | Sauvegarde document | → declaration_success |
| `/declaration-success` | GET | Succès déclaration | success.html |
| `/search` | GET | Affiche formulaire | search.html |
| `/search` | POST | Cherche documents | results.html |
| `/uploads/<filename>` | GET | Serve images | (fichier statique) |

---

## 🔐 Sécurité

- ✓ Validation des extensions de fichier (jpg, jpeg, png, gif)
- ✓ Noms de fichier sécurisés avec `secure_filename()`
- ✓ Limite de taille: 16 MB par fichier
- ✓ Session Flask pour "login" simple
- ✓ Gestion d'erreurs 404 et 500
- ⚠️ À améliorer: Authentification, validation serveur, CSRF protection

---

## 🎨 Interface Utilisateur

- **Design**: Minimaliste et fonctionnel
- **Responsive**: Adapté pour mobile et desktop (breakpoint à 768px)
- **Couleurs**: 
  - Primaire: Bleu (#3498db)
  - Texte: Gris foncé (#333)
  - Accents: Vert (#27ae60), Rouge (#e74c3c)
- **Typo**: 'Segoe UI' ou fallback sans-serif
- **CSS**: Inlinisé dans base.html (pas de fichier CSS externe)

---

## 📊 Validation des Données

### Enregistrement (/register)
- ✓ nom: obligatoire, texte
- ✓ prenom: obligatoire, texte
- ✓ email OU telephone: au moins un obligatoire
- ✓ code_pays: optionnel (dropdown prédéfini)

### Déclaration (/declare)
- ✓ user_id: obligatoire, doit exister
- ✓ type_piece: obligatoire
- ✓ nom/prenom: obligatoire
- ✓ date_naissance: obligatoire
- ✓ lieu_naissance: obligatoire
- ✓ telephone_declarant: obligatoire
- ✓ photo_piece: obligatoire, format autorisé

### Recherche (/search)
- ✓ Au moins un champ requis
- ✓ Correspondance exacte sur les champs

---

## 🚀 Démarrage Rapide

```bash
# 1. Vérifier MongoDB
mongosh

# 2. Installer dépendances
pip install -r requirements.txt

# 3. Lancer app
python app.py

# 4. Accès navigateur
http://localhost:5000
```

---

## 📝 Paramètres à Adapter

### app.py - Ligne ~20
```python
app.secret_key = 'votre_cle_secrete_ici_changez_moi'  # ⚠️ Changer en production
```

### app.py - Ligne ~41
```python
MONGODB_URI = 'mongodb://localhost:27017/'  # ⚠️ Adapter si MongoDB ailleurs
DATABASE_NAME = 'samapiece'
```

### app.py - Ligne ~745
```python
app.run(debug=True, host='localhost', port=5000)  # ⚠️ Port à adapter si nécessaire
```

---

## 📚 Pays Prédéfinis (Dropdown Enregistrement)

```
+221 (Sénégal)
+225 (Côte d'Ivoire)
+33 (France)
+212 (Maroc)
+216 (Tunisie)
+237 (Cameroun)
+256 (Ouganda)
+255 (Tanzanie)
+234 (Nigeria)
+243 (RD Congo)
```

---

## 📦 Types de Documents Prédéfinis

```
- Carte d'identité
- Passeport
- Permis de conduire
- Permis de séjour
- Titre de séjour
- Certificat de naissance
- Livret de famille
- Autre
```

---

## ✨ Fonctionnalités Principales

1. **Système d'enregistrement simple** sans email de confirmation
2. **Déclaration de documents** avec upload d'image
3. **Recherche par correspondance exacte** (nom, prénom, date/lieu naissance)
4. **Affichage des résultats** en grille responsive
5. **Contact direct** du déclarant par téléphone
6. **Gestion des uploads** sécurisée

---

## 🔄 Flux Utilisateur Complet

```
Accueil
  ↓
Créer un compte (POST /register)
  ↓
Déclarer un document (POST /declare)
  ↓
Confirmation de déclaration
  ↓
(Autre utilisateur)
  ↓
Rechercher un document (POST /search)
  ↓
Affichage résultats avec contact
  ↓
Prise contact avec déclarant (téléphone)
```

---

## 💾 Stockage des Fichiers

```
uploads/
├── .gitkeep
├── Martin_Alice_1702206000.5.jpg    # Exemple
├── Jean_Pierre_1702206015.3.png
└── ...
```

Chemin stocké en BD: `uploads/Martin_Alice_1702206000.5.jpg`

---

## 🧪 Données de Test

### Créer un utilisateur test:
- Nom: `Dupont`
- Prénom: `Jean`
- Email: `jean@example.com`
- Code: `+33`

### Déclarer un document test:
- Type: `Passeport`
- Nom: `Dupont`
- Prénom: `Jean`
- Date naissance: `1990-05-15`
- Lieu: `Paris`
- Téléphone: `+33612345678`

### Rechercher le document:
- Entrez les mêmes informations
- Vous devriez le trouver! ✓

---

## 📞 Support

Consultez:
- **README.md** pour documentation complète
- **QUICK_START.md** pour démarrage rapide
- **app.py** pour commentaires en français

---

**Version**: 1.0  
**Date**: Décembre 2025  
**Status**: ✓ Prêt pour production (après améliorations de sécurité)
