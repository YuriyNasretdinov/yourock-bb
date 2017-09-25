function delete_ad(id)
{
    Q('delete-ad', { id: id });
}

function delete_ads()
{
    var els = document.getElementsByName('ids[]');
    
    var ids = [];
    
    for(var k in els) ids.push(els[k].value);
    
    Q('delete-ads', { ids: ids });
}

function check_all()
{
    var els = document.getElementsByName('ids[]');
    
    for(var k in els)
    {
        els[k].checked = true;
    }
}

function uncheck_all()
{
    var els = document.getElementsByName('ids[]');
    
    for(var k in els)
    {
        els[k].checked = false;
    }
}