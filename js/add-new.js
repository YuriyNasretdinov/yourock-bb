if(!$)
{
    var $ = function(id)
    {
        return document.getElementById(id);
    }
}

function check_fields()
{
    if(!$('fio').value)
    {
        alert('Введите ФИО или имя организации!');
        return false;
    }
    
    if(!$('email').value.match(/[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?/))
    {
        alert('Введите корректный e-mail адрес');
        return false;
    }
    
    if(!$('header').value)
    {
        alert('Введите заголовок объявления');
        return false;
    }
    
    if(!$('body').value)
    {
        alert('Введите текст объявления');
        return false;
    }
    
    if(!$('captcha_keystring').value)
    {
        alert('Введите проверочный код!');
        return false;
    }
    
    return true;
}