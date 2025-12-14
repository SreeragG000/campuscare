// Minimal JS helpers
function confirmAndGo(url, msg){
  if(confirm(msg||'Are you sure?')){ window.location = url; }
}
