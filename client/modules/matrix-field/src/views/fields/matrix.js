define("matrix-field:views/fields/matrix", ["views/fields/base"], function (Dep) {
  
  return Dep.extend({
    
    type: "matrix",

    detailTemplate: "matrix-field:fields/matrix/detail",

    editTemplate: "matrix-field:fields/matrix/edit",

    events: {
      'click [data-action="changeValue"]': function (e) {
        e.preventDefault();
        e.stopPropagation();

        this.editGrid(e);
      },
    },

    editGrid: function (selectedTd) {
      let newPoint = selectedTd.target.id;
      let newPointIndex = newPoint.split("-");
      this.model.set(this.xPointField, parseInt(newPointIndex[0]));
      this.model.set(this.yPointField, parseInt(newPointIndex[1]));
    },

    setup: function () {
      Dep.prototype.setup.call(this);
      var actualAttributePartList =
        this.getMetadata().get(["fields", this.type, "actualFields"]) || [];
      this.matrixPartList = [];

      actualAttributePartList.forEach((item) => {
        var attribute = this.name + Espo.Utils.upperCaseFirst(item);
        this.matrixPartList.push(item);
        this[item + "Field"] = attribute;
      });
    },

    data: function () {
      var data = Dep.prototype.data.call(this);

      this.matrixPartList.forEach((item) => {
        data[item] = this.model.get(this[item + "Field"]);
      });

      if (this.isEditMode()) {
        var xArray = [];
        var yArray = [];
        for (let x = 1; x <= this.params.xSize; x++) {
          xArray.push(x);
        }

        for (let y = 1; y <= this.params.ySize; y++) {
          yArray.push(y);
        }

        data.xArray = xArray;
        data.yArray = yArray;
      }

      return data;
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      if (this.isEditMode()) {
        var xPointValue = this.model.get(this.xPointField);
        var yPointValue = this.model.get(this.yPointField);
        this.$el
          .find("#" + xPointValue + "-" + yPointValue + "")
          .removeClass("myCell");
        this.$el
          .find("#" + xPointValue + "-" + yPointValue + "")
          .addClass("mySelectedCell");
      }
    }
    
  });
});
