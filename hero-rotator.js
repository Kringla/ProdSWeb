
(function(){
  var el = document.querySelector('.hero-rotator');
  if(!el) return;
  var data = el.getAttribute('data-images');
  if(!data) return;
  var imgs;
  try { imgs = JSON.parse(data); } catch(e){ return; }
  if(!Array.isArray(imgs) || imgs.length === 0) return;

  imgs.forEach(function(src){ var i=new Image(); i.src = src; });

  function apply(a,b){
    el.style.setProperty('--hero-a', 'url(\"'+a+'\")');
    el.style.setProperty('--hero-b', 'url(\"'+b+'\")');
  }
  var style = document.createElement('style');
  style.innerHTML = '.hero-rotator::before{background-image: var(--hero-a);} .hero-rotator::after{background-image: var(--hero-b);}';
  document.head.appendChild(style);

  var i = 0;
  apply(imgs[0], imgs[(1)%imgs.length]);

  setInterval(function(){
    var next = (i+1) % imgs.length;
    el.classList.toggle('is-alt');
    setTimeout(function(){
      apply(imgs[next], imgs[(next+1)%imgs.length]);
      i = next;
    }, 700);
  }, 6000);
})();
