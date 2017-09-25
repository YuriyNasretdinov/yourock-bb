function add_new_cat()
{
    var new_name = prompt('Введите имя категории: ', '');
    if(!new_name) return false;
    
    var new_engname = prompt('Английское название для категории: ', '');
    if(!new_engname) return false;
    
    var new_description = prompt('Описание категории: ', '');
    if(new_description === false) return false;
    
    Q('add-cat', { name: new_name, engname: new_engname, description: new_description });
    
    return true;
}

function edit_cat(id)
{
    Q('get-cat', { id: id }, function(res,err)
    {
        if(err)
        {
            alert(err);
            return false;
        }
        
        var new_name = prompt('Введите новое имя категории: ', res.name);
        if(!new_name) return false;
        
        var new_engname = prompt('Английское название для категории: ', res.engname);
        if(!new_engname) return false;
        
        var new_description = prompt('Описание категории: ', res.description);
        if(!new_description) return false;
        
        Q('set-cat', { id: id, name: new_name, engname: new_engname, description: new_description });
        
        return true;
    
    });
    
    return true;
}

function delete_cat(id)
{
    Q('delete-cat', { id: id });
}

function move_cat(id, direction)
{
    Q(direction == 'up' ? 'raise-cat' : 'lower-cat', { id: id });
}

function add_scat(pid)
{
    var new_name = prompt('Введите имя подкатегории: ', '');
    if(!new_name) return false;
    
    var new_engname = prompt('Английское название для подкатегории: ', '');
    if(!new_engname) return false;
    
    var new_description = prompt('Описание подкатегории: ', '');
    if(new_description === false) return false;
    
    Q('add-scat', { pid: pid, name: new_name, engname: new_engname, description: new_description });
    
    return true;
}

function edit_scat(id)
{
    Q('get-scat', { id: id }, function(res,err)
    {
        if(err)
        {
            alert(err);
            return false;
        }
        
        var new_name = prompt('Введите новое имя подкатегории: ', res.name);
        if(!new_name) return false;
        
        var new_engname = prompt('Английское название для подкатегории: ', res.engname);
        if(!new_engname) return false;
        
        var new_description = prompt('Описание подкатегории: ', res.description);
        if(!new_description) return false;
        
        Q('set-scat', { id: id, name: new_name, engname: new_engname, description: new_description });
        
        return true;
    
    });
    
    return true;
}

function delete_scat(id)
{
    Q('delete-scat', { id: id });
}

function move_scat(id, direction)
{
    Q(direction == 'up' ? 'raise-scat' : 'lower-scat', { id: id });
}