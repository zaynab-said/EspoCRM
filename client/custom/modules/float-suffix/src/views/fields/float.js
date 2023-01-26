define('float-suffix:views/fields/float', ['views/fields/float'], function (Dep) {

    return Dep.extend({

        detailTemplate: 'float-suffix:fields/float/detail',
        
        editTemplate: 'float-suffix:fields/float/edit',
       
    });
    
});
