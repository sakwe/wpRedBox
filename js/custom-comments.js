
jQuery(document).ready(function($){

str = document.getElementById("wpbody").innerHTML;
str = str.replace(/le commentaire/g,"la proposition");
str = str.replace(/un commentaire/g,"une proposition");
str = str.replace(/commentaire/g,"proposition");
str = str.replace(/commentaires/g,"propositions");
str = str.replace(/Commentaires/g,"Propositions");
str = str.replace(/Commentaire/g,"Proposition");
str = str.replace(/Réagir/g,"Proposer");
document.getElementById("wpbody").innerHTML = str;

});
