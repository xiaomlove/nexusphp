var pos = 0;
var count = 0;

function noenter(key) 
{
	suggcont = document.getElementById("suggcontainer");
	if (suggcont.style.display == "block") 
	{
		if (key == 13) 
		{
			choiceclick(document.getElementById(pos));
			return false;
		} 
		else 
		{
			return true;
		}
	} 
	else 
	{
		return true;
	}
}

document.onclick = function () { closechoices(); }

function suggest(key,query) 
{
	if (key == 38) 
	{
		goPrev();
	} 
	else if (key == 40) 
	{
		goNext();
	} 
	else if (key != 13) 
	{
		if (query.length >= 2) {
			query = query.toLowerCase();
			if (query == 'th' || query == 'the' || query == 'the ') {
				update('');
			} else {
				ajax.get('suggest.php?q='+query,update);
			}
		} else {
			update('');
		}
	}
}

function update(result) {
	arr_keywords = new Array();
	arr_searched = new Array();
	arr = new Array();
	arr = result.split('\r\n');

	count = arr.length;
	count_keywords = 0;
	count_searched = 0;
	for (i = 0; i < count; i++) 
	{
		if(i%2 == 0)
		{
			arr_keywords[count_keywords] = arr[i];
			count_keywords++;
		}
		else
		{
			arr_searched[count_searched] = arr[i];
			count_searched++;
		}
	}
	
	if (arr_keywords.length > 10) 
	{
		count = 10;
	} 
	else 
	{
		count = arr_keywords.length;
	}

	suggdiv = document.getElementById("suggestions");
	suggcont = document.getElementById("suggcontainer");
	if (arr_keywords[0].length > 0) 
	{
		suggcont.style.display = "block";
		suggdiv.innerHTML = '';
		suggdiv.style.height = count * 20;

		for (i = 1; i <= count; i++) 
		{
			novo = document.createElement("div");
			suggdiv.appendChild(novo);
			
			novo.id = i;
			novo.style.height = "14px";
			novo.style.padding = "3px";
			novo.onmouseover = function() { select(this,true); }
			novo.onmouseout = function() { unselect(this,true); }
			novo.onclick = function() { choiceclick(this); }
			novo.value = arr_keywords[i-1];
			
			if (arr_searched[i-1] == 1)
				stime = arr_searched[i-1] + " Time";
			else
				stime = arr_searched[i-1] + " Times";
			novo.innerHTML = "<table width=100% style=\"border:0px;background-color: transparent;\"><tr><td style=\"border:0px;\" align=left><strong>" + arr_keywords[i-1] + "</strong></td><td style=\"border:0px;\" align=right>" + stime + "</td></tr></table>";	
		}
	}
	else
	{
		suggcont.style.display = "none";
		count = 0;
	}
}

function select(obj,mouse) 
{
	obj.style.backgroundColor = '#3366cc';
	obj.style.color = '#ffffff';
	if (mouse) 
	{
		pos = obj.id;
		unselectAllOther(pos);
	}
}

function unselect(obj,mouse) 
{
	obj.style.backgroundColor = '#ffffff';
	obj.style.color = '#000000';
	if (mouse) 
	{
		pos = 0;
	}
}

function goNext() 
{
	if (pos <= count && count > 0) {
		if (document.getElementById(pos)) {
			unselect(document.getElementById(pos));
		}
		pos++;
		if (document.getElementById(pos)) {
			select(document.getElementById(pos));
		} else {
			pos = 0;
		}
	}
}

function goPrev() 
{
	if (count > 0) 
	{
		if (document.getElementById(pos)) 
		{
			unselect(document.getElementById(pos));
			pos--;
			if (document.getElementById(pos)) 
			{
				select(document.getElementById(pos));
			} 
			else 
			{
				pos = 0;
			}
		} 
		else 
		{
			pos = count;
			select(document.getElementById(count));
		}
	}
}

function choiceclick(obj) 
{
	document.getElementById("searchinput").value = obj.value;
	count = 0;
	pos = 0;
	suggcont = document.getElementById("suggcontainer");
	suggcont.style.display = "none";
	document.getElementById("searchinput").focus();
}

function closechoices() 
{
	suggcont = document.getElementById("suggcontainer");
	if (suggcont.style.display == "block") 
	{
		count = 0;
		pos = 0;
		suggcont.style.display = "none";
	}
}

function unselectAllOther(id) 
{
	for (i = 1; i <= count; i++) 
	{
		if (i != id) 
		{
			document.getElementById(i).style.backgroundColor = '#ffffff';
			document.getElementById(i).style.color = '#000000';
		}
	}
}
