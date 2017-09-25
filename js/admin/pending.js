function delete_pending_ad(id)
{
    Q('delete-pending-ad', { id: id });
}

function delete_selected()
{
    var els = document.getElementsByName('ids[]');
    
    var ids = [];
    
    for(var k in els) if(els[k].checked) ids.push(els[k].value);
    
    if(!ids.length)
    {
        alert('Выберите хотя бы одно объявление!');
        return false;
    }
    
    Q('delete-pending-ads', { ids: ids });
}

function confirm_selected()
{
    var els = document.getElementsByName('ids[]');
    
    var ids = [];
    
    for(var k in els) if(els[k].checked) ids.push(els[k].value);
    
    if(!ids.length)
    {
        alert('Выберите хотя бы одно объявление!');
        return false;
    }
    
    Q('confirm-pending-ads', { ids: ids });
}