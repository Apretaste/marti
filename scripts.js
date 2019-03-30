$(document).ready(function(){
    $('.materialboxed').materialbox();
    $('.modal').modal();
  });
  
function sendSearch() {
  let query = $('#searchQuery').val().trim();
  if(query.length >= 2){
      apretaste.send({
          'command':'MARTI BUSCAR',
          'data':{'busqueda' : query, 'isCategory':false}
      });
  }
    else
        showToast('Minimo 2 caracteres');
}
