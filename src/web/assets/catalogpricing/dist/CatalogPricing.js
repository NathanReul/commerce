!function(){function t(e){return t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},t(e)}"undefined"===t(Craft.Commerce)&&(Craft.Commerce={}),Craft.Commerce.CatalogPricing=Garnish.Base.extend({$clearSearchBtn:null,$filterBtn:null,$search:null,$searchContainer:null,$tableContainer:null,filterHuds:{},searchTimeout:null,searching:!1,view:null,defaults:{},init:function(t,e,i){var n=this;this.view=t,this.$tableContainer=e,this.$searchContainer=this.view.find(".search-container:first"),this.$search=this.$searchContainer.find("input:first"),this.$clearSearchBtn=this.$searchContainer.children(".clear-btn:first"),this.$filterBtn=this.$searchContainer.children(".filter-btn:first"),this.setSettings(i,this.defaults),this.settings.filterBtnActive&&this.$filterBtn.addClass("active"),this.addListener(this.$filterBtn,"click","showFilterHud"),this.addListener(this.$search,"input",(function(){!n.searching&&n.$search.val()?n.startSearching():n.searching&&!n.$search.val()&&n.stopSearching(),n.searchTimeout&&clearTimeout(n.searchTimeout),n.searchTimeout=setTimeout(n.updateTableIfSearchTextChanged.bind(n),500)})),this.addListener(this.$clearSearchBtn,"click",(function(){n.clearSearch(!0),Garnish.isMobileBrowser(!0)||n.$search.trigger("focus")}))},startSearching:function(){this.$clearSearchBtn.removeClass("hidden"),this.searching=!0},clearSearch:function(t){this.searching&&(this.$search.val(""),this.searchTimeout&&clearTimeout(this.searchTimeout),this.stopSearching(),t?this.updateTableIfSearchTextChanged():this.searchText=null)},stopSearching:function(){this.$clearSearchBtn.addClass("hidden"),this.searching=!1},showFilterHud:function(){this.getFilterHud()?this.getFilterHud().show():(this.createFilterHud(),this.updateFilterBtn())},updateFilterBtn:function(){this.$filterBtn.removeClass("active"),this.getFilterHud()?(this.$filterBtn.attr("aria-controls",this.getFilterHud().id).attr("aria-expanded",this.getFilterHud().showing?"true":"false"),(this.getFilterHud().showing||this.getFilterHud().hasRules())&&this.$filterBtn.addClass("active")):this.$filterBtn.attr("aria-controls",null)},serializeConditionForm:function(){if(!this.getFilterHud())return null;var t=this.getFilterHud().$body.find(".condition-container:first"),e={};return $.map(t.serializeArray(),(function(t){var i=t.name.match(/[a-zA-Z0-9_\\]+|(?=\[\])/g);if(console.log(i),i.length>1){for(var n=e,a=i.pop(),s=0;s<i.length;s++){var r=i[s];n[r]=n[r]?n[r]:""==a?[]:{},n=n[r]}""==a?(n=Array.isArray(n)?n:[]).push(t.value):n[a]=t.value}else e[i.pop()]=t.value})),e},getFilterHudKey:function(){return"site-".concat(this.settings.siteId)},createFilterHud:function(){this.filterHuds[this.getFilterHudKey()]=new Craft.Commerce.CatalogPricingHud(this,this.settings.siteId)},getFilterHud:function(){return-1===Object.keys(this.filterHuds).indexOf(this.getFilterHudKey())?null:this.filterHuds[this.getFilterHudKey()]},destroyFilterHud:function(){this.getFilterHud()&&delete this.filterHuds[this.getFilterHudKey()]},updateTableIfSearchTextChanged:function(){this.searchText!==(this.searchText=this.searching?this.$search.val():null)&&this.updateTable()},updateTable:function(){var t=this,e={searchText:this.$search.val(),siteId:this.settings.siteId,condition:this.serializeConditionForm()};Craft.sendActionRequest("POST","commerce/catalog-pricing/prices",{data:e}).then((function(e){e.data&&e.data.tableHtml&&(Craft.appendHeadHtml(e.data.headHtml),Craft.appendBodyHtml(e.data.bodyHtml),t.$tableContainer.html(e.data.tableHtml))}))}}),Craft.Commerce.CatalogPricingHud=Garnish.HUD.extend({view:null,siteId:null,id:null,loading:!0,serialized:null,$clearBtn:null,cleared:!1,init:function(t,e){var i=this;this.view=t,this.siteId=e,this.id="filter-".concat(Math.floor(1e9*Math.random()));var n=$("<div/>").append($("<div/>",{class:"spinner"})).append($("<div/>",{text:Craft.t("app","Loading"),class:"visually-hidden","aria-role":"alert"}));this.base(this.view.$filterBtn,n,{hudClass:"hud element-filter-hud loading"}),this.$hud.attr({id:this.id,"aria-live":"polite","aria-busy":"false"}),this.$tip.remove(),this.$tip=null,this.$body.on("submit",(function(t){t.preventDefault(),i.hide()})),Craft.sendActionRequest("POST","commerce/catalog-pricing/filter",{data:{condition:this.view.settings.condition,id:"".concat(this.id,"-filters")}}).then((function(t){i.loading=!1,i.$hud.removeClass("loading"),n.remove(),i.$main.append(t.data.hudHtml),Craft.appendHeadHtml(t.data.headHtml),Craft.appendBodyHtml(t.data.bodyHtml),i.view.settings.condition=t.data.condition,i.serialized=i.view.serializeConditionForm();var e=$("<div/>",{class:"flex flex-nowrap"}).appendTo(i.$main);$("<div/>",{class:"flex-grow"}).appendTo(e),i.$clearBtn=$("<button/>",{type:"button",class:"btn",text:Craft.t("app","Cancel")}).appendTo(e),$("<button/>",{type:"submit",class:"btn secondary",text:Craft.t("app","Apply")}).appendTo(e),i.$clearBtn.on("click",(function(){i.clear()})),i.$hud.find(".condition-container").on("htmx:beforeRequest",(function(){i.setBusy()})),i.$hud.find(".condition-container").on("htmx:load",(function(){i.setReady(),i.updateSizeAndPosition(!0)})),i.setFocus()})).catch((function(){Craft.cp.displayError(Craft.t("app","A server error occurred."))})),this.$hud.css("position","fixed"),this.addListener(Garnish.$win,"scroll,resize",(function(){i.updateSizeAndPosition(!0)}))},addListener:function(t,e,i,n){t===this.$main&&"resize"===e||this.base(t,e,i,n)},setBusy:function(){this.$hud.attr("aria-busy","true"),$("<div/>",{class:"visually-hidden",text:Craft.t("app","Loading")}).insertAfter(this.$main.find(".htmx-indicator"))},setReady:function(){this.$hud.attr("aria-busy","false")},setFocus:function(){Garnish.setFocusWithin(this.$main)},clear:function(){this.cleared=!0,this.destroy(),this.hide()},updateSizeAndPositionInternal:function(){var t,e=this.view.$searchContainer[0].getBoundingClientRect(),i=Garnish.$win.height(),n=i-e.bottom;this.$body.height()>n&&(t=i-e.bottom-10),this.$hud.css({width:this.view.$searchContainer.outerWidth()-2,top:e.top+this.view.$searchContainer.outerHeight(),left:e.left+1,height:t?"".concat(t,"px"):"unset",overflowY:t?"scroll":"unset"})},onShow:function(){this.base(),this.$clearBtn&&this.hasRules()?this.$clearBtn.text(Craft.t("app","Clear")):this.$clearBtn&&!this.hasRules()&&this.$clearBtn.text(Craft.t("app","Cancel")),this.view.updateFilterBtn(),this.setFocus()},onHide:function(){this.base(),this.serialized!==(this.serialized=this.view.serializeConditionForm())&&this.view.updateTable(),this.cleared||(this.$hud.detach(),this.$shade.detach()),this.view.updateFilterBtn(),this.view.$filterBtn.focus()},hasRules:function(){return 0!==this.$main.has(".condition-rule").length},destroy:function(){this.base(),this.view.getFilterHud()&&this.view.destroyFilterHud()}})}();
//# sourceMappingURL=CatalogPricing.js.map