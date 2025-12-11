# Guide Rapide - Samapiece

## Démarrage en 5 minutes

### Installation rapide

```bash
# 1. Cloner le projet
git clone https://github.com/vrolingmendy/Guisnako-samapiec.git
cd samapiece

# 2. Créer et activer l'environnement virtuel
python -m venv .venv
.\.venv\Scripts\Activate.ps1  # Windows
source .venv/bin/activate      # macOS/Linux

# 3. Installer les dépendances
pip install -r requirements.txt

# 4. Lancer l'application
python app.py
```

L'application est maintenant accessible sur: **http://localhost:5000**

---

## Comptes de démonstration

### Administrateur
- **Email:** admin@samapiece.com
- **Mot de passe:** admin123

### Utilisateur test
- **Email:** test@example.com
- **Mot de passe:** Test@123!

---

## Fonctionnalités principales

### En tant qu'utilisateur

1. **S'inscrire**
   - Cliquer sur "Inscription"
   - Remplir le formulaire
   - Vérifier l'email (optionnel)

2. **Déclarer un document**
   - Aller à "Déclarer un document"
   - Sélectionner le type
   - Entrer les informations
   - Ajouter une photo
   - Cliquer "Valider"

3. **Chercher un document**
   - Aller à "Rechercher"
   - Utiliser les filtres
   - Consulter la liste
   - Cliquer sur un document pour plus d'infos

4. **Créer une alerte**
   - Utiliser la barre de recherche
   - Sauvegarder comme alerte
   - Recevoir les correspondances par email

### En tant qu'administrateur

1. **Se connecter**
   - Aller à "Admin"
   - Entrer les identifiants admin
   - Cliquer "Connexion"

2. **Consulter le tableau de bord**
   - Statistiques globales
   - Dernières activités
   - Raccourcis vers gestion

3. **Gérer les documents**
   - Aller à "Documents perdus"
   - Consulter les listes
   - Cliquer pour plus de détails
   - Voir les informations du déclarant

4. **Gérer les alertes**
   - Aller à "Alertes"
   - Voir les correspondances
   - Cliquer pour détails
   - Contacter les déclarants

5. **Gérer les utilisateurs**
   - Aller à "Utilisateurs"
   - Activer/suspendre comptes
   - Consulter les statistiques

---

## Erreurs courantes et solutions

### Erreur: "Erreur de connexion"

**Cause:** Mauvais identifiant ou mot de passe  
**Solution:**
1. Vérifier l'email
2. Vérifier le mot de passe
3. Vérifier les majuscules/minuscules
4. Essayer "Mot de passe oublié"

### Erreur: "Compte bloqué"

**Cause:** 5 tentatives échouées  
**Solution:**
1. Attendre 30 minutes
2. Vérifier la connexion internet
3. Essayer sur un autre navigateur

### Erreur: "Fichier trop volumineux"

**Cause:** Image > 16 MB  
**Solution:**
1. Réduire la taille de l'image
2. Utiliser un format supporté (JPG, PNG, GIF)
3. Vérifier la qualité de l'image

### Erreur: "Formulaire invalide"

**Cause:** Données manquantes ou invalides  
**Solution:**
1. Vérifier tous les champs (astérisque = obligatoire)
2. Vérifier le format email
3. Vérifier les données entrées
4. Recharger la page

---

## Questions fréquemment posées

### Q: Comment récupérer mon document?

**R:** 
1. Cliquer sur le document
2. Noter le code de récupération
3. Contacter directement le déclarant
4. Vous identifier avec le code

### Q: Comment modifier ma déclaration?

**R:** 
1. Aller à "Mon compte"
2. Cliquer sur le document
3. Cliquer "Modifier"
4. Sauvegarder les changements

### Q: Combien de temps les documents restent-ils disponibles?

**R:** Les documents restent disponibles jusqu'à:
- Marqués comme "récupérés"
- 90 jours après la déclaration (archivage automatique)
- Suppression manuelle par l'administrateur

### Q: Comment signaler une alerte?

**R:**
1. Utiliser la barre de recherche
2. Cliquer sur "Créer une alerte"
3. Entrer vos informations
4. Valider
5. Vous recevrez un email à chaque correspondance

### Q: L'application est-elle sûre?

**R:** Oui, Samapiece utilise:
- Chiffrement des mots de passe
- Protection contre les attaques brute force
- Validation des données
- En-têtes de sécurité

---

## Raccourcis clavier

| Touche | Action |
|--------|--------|
| `/` | Recherche rapide |
| `?` | Affiche l'aide |
| `Esc` | Ferme les modales |
| `Ctrl+S` | Sauvegarde le formulaire |

---

## Support et contact

### Besoin d'aide?

1. Consulter cette documentation
2. Vérifier les FAQs
3. Contacter le support: support@samapiece.com
4. Créer une issue: https://github.com/vrolingmendy/Guisnako-samapiec/issues

### Signaler un bug

1. Décrire le problème précisément
2. Fournir des captures d'écran
3. Indiquer le navigateur et l'OS utilisés
4. Envoyer à: bugs@samapiece.com

---

## Astuces et bonnes pratiques

### Pour les utilisateurs

✓ Utiliser des photos claires et bien éclairées  
✓ Entrer des informations exactes et complètes  
✓ Vérifier votre email régulièrement  
✓ Garder votre mot de passe confidentiel  
✓ Utiliser une adresse email vérifiée  

### Pour les administrateurs

✓ Sauvegarder les données régulièrement  
✓ Vérifier les logs de sécurité  
✓ Archiver les anciens documents  
✓ Mettre à jour la liste des alertes  
✓ Communiquer régulièrement avec les utilisateurs  

---

## Ressources supplémentaires

- **Documentation complète:** [README_FR.md](README_FR.md)
- **Guide de développement:** [GUIDE_DEVELOPPEMENT.md](GUIDE_DEVELOPPEMENT.md)
- **Configuration avancée:** [CONFIG_FR.md](CONFIG_FR.md)

---

**Dernière mise à jour:** 11 décembre 2025  
**Version:** 1.0.0  
**Langue:** Français

Bienvenue sur Samapiece! 🎉
