!function(){function e(e,t,n){t.is&&t.getCustomData("block_processed")||(t.is&&CKEDITOR.dom.element.setMarker(n,t,"block_processed",!0),e.push(t))}function t(t,n){var l,i=(l=CKEDITOR.tools.extend({},CKEDITOR.dtd.$blockLimit),t.config.div_wrapTable&&(delete l.td,delete l.th),l),o=CKEDITOR.dtd.div;function a(e){var n=t.elementPath(e).blockLimit;if(n.isReadOnly()&&(n=n.getParent()),t.config.div_wrapTable&&n.is(["td","th"])){var l=t.elementPath(n.getParent());n=l.blockLimit}return n}function r(){this.foreach((function(e){/^(?!vbox|hbox)/.test(e.type)&&(e.setup||(e.setup=function(t){e.setValue(t.getAttribute(e.id)||"",1)}),e.commit||(e.commit=function(t){var n=this.getValue();"dir"==e.id&&t.getComputedStyle("direction")==n||(n?t.setAttribute(e.id,n):t.removeAttribute(e.id))}))}))}function d(t){var n,l,r,d=[],s={},m=[],c=t.getSelection(),u=c.getRanges(),g=c.createBookmarks();for(l=0;l<u.length;l++)for(r=u[l].createIterator();n=r.getNextParagraph();)if(n.getName()in i&&!n.isReadOnly()){var h,f=n.getChildren();for(h=0;h<f.count();h++)e(m,f.getItem(h),s)}else{for(;!o[n.getName()]&&!n.equals(u[l].root);)n=n.getParent();e(m,n,s)}CKEDITOR.dom.element.clearAllMarkers(s);var v,p,b=function(e){for(var t,n=[],l=null,i=0;i<e.length;i++){var o=a(t=e[i]);o.equals(l)||(l=o,n.push([])),n[n.length-1].push(t)}return n}(m);for(l=0;l<b.length;l++){var y=b[l][0];for(v=y.getParent(),h=1;h<b[l].length;h++)v=v.getCommonAncestor(b[l][h]);for(p=new CKEDITOR.dom.element("div",t.document),h=0;h<b[l].length;h++){for(y=b[l][h];!y.getParent().equals(v);)y=y.getParent();b[l][h]=y}for(h=0;h<b[l].length;h++)(y=b[l][h]).getCustomData&&y.getCustomData("block_processed")||(y.is&&CKEDITOR.dom.element.setMarker(s,y,"block_processed",!0),h||p.insertBefore(y),p.append(y));CKEDITOR.dom.element.clearAllMarkers(s),d.push(p)}return c.selectBookmarks(g),d}function s(e){var n=this.getDialog(),l=n._element&&n._element.clone()||new CKEDITOR.dom.element("div",t.document);this.commit(l,!0);for(var i,o=(e=[].concat(e)).length,a=0;a<o;a++)(i=n.getContentElement.apply(n,e[a].split(":")))&&i.setup&&i.setup(l,!0)}var m={},c=[];return{title:t.lang.div.title,minWidth:400,minHeight:165,contents:[{id:"info",label:t.lang.common.generalTab,title:t.lang.common.generalTab,elements:[{type:"hbox",widths:["50%","50%"],children:[{id:"elementStyle",type:"select",style:"width: 100%;",label:t.lang.div.styleSelectLabel,default:"",items:[[t.lang.common.notSet,""]],onChange:function(){s.call(this,["info:elementStyle","info:class","advanced:dir","advanced:style"])},setup:function(e){for(var n in m)m[n].checkElementRemovable(e,!0,t)&&this.setValue(n,1)},commit:function(e){var n;(n=this.getValue())?m[n].applyToObject(e,t):e.removeAttribute("style")}},{id:"class",type:"text",requiredContent:"div(cke-xyz)",label:t.lang.common.cssClass,default:""}]}]},{id:"advanced",label:t.lang.common.advancedTab,title:t.lang.common.advancedTab,elements:[{type:"vbox",padding:1,children:[{type:"hbox",widths:["50%","50%"],children:[{type:"text",id:"id",requiredContent:"div[id]",label:t.lang.common.id,default:""},{type:"text",id:"lang",requiredContent:"div[lang]",label:t.lang.common.langCode,default:""}]},{type:"hbox",children:[{type:"text",id:"style",requiredContent:"div{cke-xyz}",style:"width: 100%;",label:t.lang.common.cssStyle,default:"",commit:function(e){e.setAttribute("style",this.getValue())}}]},{type:"hbox",children:[{type:"text",id:"title",requiredContent:"div[title]",style:"width: 100%;",label:t.lang.common.advisoryTitle,default:""}]},{type:"select",id:"dir",requiredContent:"div[dir]",style:"width: 100%;",label:t.lang.common.langDir,default:"",items:[[t.lang.common.notSet,""],[t.lang.common.langDirLtr,"ltr"],[t.lang.common.langDirRtl,"rtl"]]}]}]}],onLoad:function(){r.call(this);var e=this,n=this.getContentElement("info","elementStyle");t.getStylesSet((function(l){var i,o;if(l)for(var a=0;a<l.length;a++){var r=l[a];r.element&&"div"==r.element&&(i=r.name,m[i]=o=new CKEDITOR.style(r),t.filter.check(o)&&(n.items.push([i,i]),n.add(i,i)))}n[n.items.length>1?"enable":"disable"](),setTimeout((function(){e._element&&n.setup(e._element)}),0)}))},onShow:function(){"editdiv"==n&&this.setupContent(this._element=CKEDITOR.plugins.div.getSurroundDiv(t))},onOk:function(){for(var e=(c="editdiv"==n?[this._element]:d(t)).length,l=0;l<e;l++)this.commitContent(c[l]),!c[l].getAttribute("style")&&c[l].removeAttribute("style");this.hide()},onHide:function(){"editdiv"==n&&this._element.removeCustomData("elementStyle"),delete this._element}}}CKEDITOR.dialog.add("creatediv",(function(e){return t(e,"creatediv")})),CKEDITOR.dialog.add("editdiv",(function(e){return t(e,"editdiv")}))}();