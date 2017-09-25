function add_region()
{
    var new_name = prompt('Название области', '');
    if(!new_name) return;
    
    Q('add-region', { name: new_name });
}

function edit_region(id, name)
{
    var new_name = prompt('Название области: ', name);
    if(!new_name) return;
    
    Q('edit-region', { id: id, name: new_name });
}

function edit_city(id, name)
{
    var new_name = prompt('Название города: ', name);
    if(!new_name) return;
    
    Q('edit-city', { id: id, name: new_name });
}

function add_city(region)
{
    var new_name = prompt('Название города: ', name);
    if(!new_name) return;
    
    Q('add-city', { region: region, name: new_name });
}

function delete_region(id)
{
    Q('delete-region', { id: id });
}

function delete_city(id)
{
    Q('delete-city', { id: id });
}