// per-character karaoke scroll
(function(){
  class SplitLines extends HTMLElement {
    constructor(){ super();
      this.attachShadow({mode:'open'});
      this._c=document.createElement('span');
      this.shadowRoot.appendChild(this._c);
      this._letters=[];
      this._spaceWidth=0;
    }
    connectedCallback(){
      const text=(this.textContent||'').replace(/\\s+/g,' ').trim();
      this.innerHTML=text;
      const line=document.createElement('span');
      line.setAttribute('part','line');
      this._c.appendChild(line);

      for(const ch of text){
        const s=document.createElement('span');
        s.setAttribute('part','letter');
        s.textContent=ch;
        line.appendChild(s);
        this._letters.push(s);
        if (ch === ' ') {
          s.style.whiteSpace = 'pre';
          this._spaceWidth = s.offsetWidth;
        }
      }
    }
    get letters(){ return this._letters; }
  }
  customElements.define('split-lines', SplitLines);

  class ReadingText extends HTMLElement {
    constructor(){ super();
      this._letters=[];
      this._speed=parseFloat(this.getAttribute('reading-speed')||'1');
      this._startOpacity=parseFloat(this.getAttribute('text-start-opacity')||'0.2');
      this._last=-1;
      this._scroll=this._scroll.bind(this);
      this._spaceWidth=0;
    }
    connectedCallback(){
      const sl=this.querySelector('split-lines'); if(!sl)return;
      queueMicrotask(()=>{ this._letters=sl.letters||[]; this._letters.forEach(l=>l.style.opacity=this._startOpacity);
        this._spaceWidth = sl._spaceWidth || 0;
        this._update(); addEventListener('scroll',this._scroll,{passive:true}); addEventListener('resize',this._scroll);
      });
    }
    disconnectedCallback(){ removeEventListener('scroll',this._scroll); removeEventListener('resize',this._scroll); }
    _progress(){
      // find the nearest sticky wrapper section for consistent scroll tracking
      const section = this.closest('.reading-section') || this;
      const rect = section.getBoundingClientRect();
      const vh = window.innerHeight;
      const start = vh * 0.25;       // start activating when top enters quarter view
      const end = vh * 1.75;         // finish when bottom is past this point
      const progress = (vh - rect.top - start) / (rect.height - start - end/2);
      return Math.max(0, Math.min(1, progress));
    }    
    _update(){ if(!this._letters.length)return;
      const p=this._progress();
      const total=this._letters.length;
      const count=Math.floor(p*total*this._speed);
      if(count===this._last)return;
      this._last=count;
      for(let i=0;i<total;i++) {
        const el=this._letters[i];
        if (i < count) {
          el.classList.add('on');
          el.style.opacity='1';
        } else {
          el.classList.remove('on');
          el.style.opacity=this._startOpacity;}
        }
      }
    _scroll(){this._update();}
  }
  customElements.define('reading-text', ReadingText);
})();