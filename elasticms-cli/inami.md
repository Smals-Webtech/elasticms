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
    * On vérifie d abord le parent (colonne A)
      * que l'url n'est pas en réalité une redirection vers une nouvelle url (une url inconnu jusqu'ici)
        * Si oui on considère cette url à la place de l'url qui est une redirection
      * On vérifier que l'url renvoit un 200
      * On vérifie que cette url est bien présente dans ems (on ne peut pas ajouter une page inconnue)
    * On vérifie que l'url (colonne B)
      * on verifie qu'il n y a pas dans la même fraterie un lien identique,et dont l'un est une Page en hide et l autre AuthoredLinkPlain et non hide
        * dans ce cas on garde Page et on ignore AuthoredLinkPlain, mais on copie les info title et hide de AuthoredLinkPlain vers Page.
      * n'est pas en réalité une redirection vers une nouvelle url (une url inconnu jusqu'ici)
          * Si oui on considère cette url à la place  de l'url qui est une redirection
      * On vérifier que l'url renvoit un 200 
      * On vérifie que cette url est bien présente dans ems (on ne peut pas ajouter une page inconnue)
      * on vérie dans le cas de Type Page ou Area, pour lequel la based (parent) de l'url (colonne B) correspond parent (colonne A)  
      * On vérifie que le parent n a pas généré d erreur avant
        * Si il existe on recherche le parent dans la structure en cours de génération
          * Sur base du path du parent on cherche l'ouuid
          * Sur base de l'ouuid on génère (seed+hash) l'identifiant que devrait avoir le noeud dans la structure en cours de génération
      * On détermine donc le parent dans la structure ou l'on doit insérer la fraterie
      * Chaque membre de la fraterie (encore valide) est ajouté au noeud
        * La langue est déterminée par le premier slug de l'url (e.g. /fr/)

## Outputs

Un fichier `menu.json` contient un JSON doublement encodé qu'il suffit de copier/coller dans une structure (créer un draft, save as draft, Raw edit avec les droits super user).

Un fichier `ouuids.json` qui contient un cache qui permet de determiner si une url a été importée ou pas et avec quel ouuid et quel type.

Un fichier `Import-Report.xlsx` qui liste toutes les erreurs rencontrées pendant la génération de la structure:

3 Categories d erreurs : 
  * ERROR : auraît peut-être du être importé cette ligne du fichier
     * erreurs `parent_http_error` correspondent à des urls parent (colonne A) qui n'ont pas pu être téléchargées depuis l'actuel site web de l'INAMI, d'après moi ces urls ne doivent pas être reprises
     * erreurs `http_error` correspondent à des urls (colonne B) qui n'ont pas pu être téléchargées depsui l'actuel site web de l'INAMI, d'après moi ces urls ne doivent pas être reprises
     * erreurs `not-imported` impossible de trouver un document correspondant à l url (colonne B) de cette ligne dans ems
     * erreurs `parent_not_imported` impossible de trouver un document correspondant à l url parent (colonne A) de cette ligne dans ems
     * erreurs `parent_not_defined` l'url (colonne B) n a pas posé de soucis, mais nous avions une erreur sur le parent et n'a donc pu ête importé
    
  * IGNORE: on ignore volontairement cette ligne du fichier :
    * erreurs `parent_mismatch` correspondent à des documents de Type Page ou Area, pour lequel la based (parent) de l'url (colonne B) ne correspond pas au parent (colonne A)
    * erreurs `duplicate-menu` correspondent à des documents qui ont un un frère avec la même url(colonne B) mais dont un est une Page en hide et l autre AuthoredLinkPlain et non hide
      * dans ce cas on garde Page et on ignore AuthoredLinkPlain, mais on copie les info title et hide de AuthoredLinkPlain vers Page.
    * erreurs `sort_order_missing` correspondent à ligne dans l excel dont le SortOrder n est pas défini (généralement quand la colonne A (url parent) == la colonne B (url du document))
    * erreurs `depth_zero` correspondent à ligne dans l excel dont le type est  AuthoredLinkPlain et le depth = 0 mais après les autres processus sur AuthoredLinkPlain.

  * INFO: information supplémentaires erreurs sur les lignes ou sur le résultats final :
    * erreurs `parent_redirect` correspondent à des urls parent (colonne A)  qui sont des redirections vers des urls qui ont déjà été ajoutées à la structure, d'après moi ces urls ne doivent pas être reprises
    * erreurs `redirect` correspondent à des urls parent (colonne B) qui sont des redirections vers des urls qui ont déjà été ajoutées à la structure, d'après moi ces urls ne doivent pas être reprises
    * erreurs `hide_mismatch` correspondent à des documents qui sont repris plusieurs fois dans une même fraterie (soit plusieurs fois avec la même url soit avec les variants par langue)
      * dans ce cas le noeud est toujours forcé à `hide = false`
    * erreurs `menu_mismatch` sur le résultats final on vérifie que tous les entrées de menu on un label_fr et label_nl si c'est pas le cas j'obtiens cette erreur
    * erreurs `too_much_import` une même url est associée à plusieurs document dans ems (et donc plusieurs ouuid lié) c'est du au page ou le swith de lang vers NL pointait sur la home ou le décalage dans stastique.


Attention, il est impossible de déterminer si les fraterie sont complète ou pas. J'ai documenté des exemples de fraterie manifestement incomplète.

