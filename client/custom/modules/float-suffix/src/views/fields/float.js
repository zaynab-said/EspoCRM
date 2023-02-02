define('float-suffix:views/fields/float', ['views/fields/float'], function (Dep) {

    return Dep.extend({

        afterRender: function () {
          Dep.prototype.afterRender.call(this);

          if (this.model.get(this.name) && "Suffix" in this.params && this.params.Suffix != null) {

            this.$el.append('<span>   '+this.params.Suffix+'</span>');

          }

        }
       
    });
    
});
