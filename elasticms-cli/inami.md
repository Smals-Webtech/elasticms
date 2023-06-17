A NE PAS MERGER dans le repo publique.

# Générer une structure INAMI depuis un export WebPlusNav

Pour lancer la commande de génération de structure pour le site de l'INAMI, il faut:
 * être connecter sur le VPN de Smals (j'ai pas essayé depuis les bureaux de Smals)
 * définir les variables d'environment suivantes:

```
export HTTPS_PROXY=http://proxyusr.smals.be:8080
export HTTP_PROXY=http://proxyusr.smals.be:8080
export NO_PROXY=.socialsecurity.be,localhost,.webagency.be,.onss.fgov.be,.rsz.fgov.be,.smals.be,localhost
```

Si vous avez déjà lancer une fois cette commande, il peut être intéressant d'effacer le fichier ouuids.json 
(il s'agit d'un cache qui fait le lien entre les urls importées dans ems et leurs OUUIDs respectifs).

Il reste à lancer la commande suivante:

```
php bin/console emscli:file-reader:generate-structure /home/dockerce/documents/inami/WebPlusNav_230425.xlsx
```

Il faut compter une bonne demi heure

## Algo
 
  * Charge un mapping qui permet de déterminer l'ouuid et le type pour une url
  * Charge l'excel dans un tableau
  * Vérifie que la première ligne de cet excel correspond exactement aux headers attendus
  * Parcours le tableau de l'excel ligne par ligne et le regroupe par fraterie (brotherhood)
    * C'est dire toutes séries de lignes entre 
      * une dont la colonne SorteOrder vaut 1, ligne comprise
      * la ligne qui suit dont la colonne SortOrder vaut aussi 1, non comprise
  * Pour chaques élements de la fraterie
    * On vérifie que l'url n'est pas en réalité une redirection vers une nouvelle url (une url inconnu jusqu'ici)
      * Si oui on considère cette url à la place  de l'url qui est une redirection
    * On vérifier que l'url renvoit un 200 
    * On vérifie que cette url est bien présente dans ems (on ne peut pas ajouter une page inconnue)
    * On cherche dans la fraterie la première ligne de 'Type' Area ou Page
      * Pour cette ligne, on parse le dom de l'url à la recherche du dernier noeud du '.breadcrumb a'
        * Si il n'existe pas la fraterie est ajoutée à la racine de la structure
        * Si il existe on recherche le parent dans la structure en cours de génération
          * Sur base du path du parent on cherche l'ouuid
          * Sur base de l'ouuid on génère (seed+hash) l'identifiant que devrait avoir le noeud dans la structure en cours de génération
      * On détermine donc le parent dans la structure ou l'on doit insérer la fraterie
      * Chaque membre de la fraterie (encore valide) est ajouté au noeud
        * La langue est déterminée par le premier slug de l'url (e.g. /fr/)

## Outputs

Un fichier `menu.json` contient un JSON doublement encodé qu'il suffit de copier/coller dans une structure (créer un draft, save as draft, Raw edit avec les droits super user).

Un fichier `ouuids.json` qui contient un cache qui permet de determiner si une url a étét importée ou pas et avec quel ouuid et quel type.

Un fichier `Import-Report.xlsx` qui liste toutes les erreurs rencontrées pendant la génération de la structure:

 * erreurs `http_error` correspondent à des urls qui n'ont pas pu être téléchargées depsui l'actuel site web de l'INAMI, d'après moi ces urls ne doivent pas être reprises
 * erreurs `redirect` correspondent à des urls qui sont des redirections vers des urls qui ont déjà été ajoutées à la structure, d'après moi ces urls ne doivent pas être reprises
 * erreurs `hide_mismatch` correspondent à des documents qui sont repris plusieurs fois dans une même fraterie (soit plusieurs fois avec la même url soit avec les variants par langue)
   * dans ce cas le noeud est toujours forcé à `hide = false`
 * erreurs `no_page_in_menu` impossible de dtéterminer le parent de la fraterie car elle ne contient pas ligne Page ou Area
 * erreurs `not-imported` impossible de trouver un document correspondant à cette ligne dans ems
 * erreurs `parent_not_imported` impossible de trouver un document correspondant au parent de cette ligne dans ems
 * erreurs `parent_not_in_structure` impossible de trouver un document correspondant au parent de cette ligne dans la structure en cours de génération



Attention, il est impossible de déterminer si les fraterie sont complète ou pas. J'ai documenté des exemples de fraterie manifestement incomplète.

