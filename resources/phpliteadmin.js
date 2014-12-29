//initiated autoincrement checkboxes
function initAutoincrement()
{
	var i=0;
	while(document.getElementById('i'+i+'_autoincrement')!=undefined)
	{
		document.getElementById('i'+i+'_autoincrement').disabled = true;
		i++;
	}
}
//makes sure autoincrement can only be selected when integer type is selected
function toggleAutoincrement(i)
{
	var type = document.getElementById('i'+i+'_type');
	var primarykey = document.getElementById('i'+i+'_primarykey');
	var autoincrement = document.getElementById('i'+i+'_autoincrement');
	if(!autoincrement) return false;
	if(type.value=='INTEGER' && primarykey.checked)
		autoincrement.disabled = false;
	else
	{
		autoincrement.disabled = true;
		autoincrement.checked = false;
	}
}
function toggleNull(i)
{
	var pk = document.getElementById('i'+i+'_primarykey');
	var notnull = document.getElementById('i'+i+'_notnull');
	if(pk.checked)
	{
		notnull.disabled = true;
		notnull.checked = true;
	}
	else
	{
		notnull.disabled = false;
	}
}
//finds and checks all checkboxes for all rows on the Browse or Structure tab for a table
function checkAll(field)
{
	var i=0;
	while(document.getElementById('check_'+i)!=undefined)
	{
		document.getElementById('check_'+i).checked = true;
		i++;
	}
}
//finds and unchecks all checkboxes for all rows on the Browse or Structure tab for a table
function uncheckAll(field)
{
	var i=0;
	while(document.getElementById('check_'+i)!=undefined)
	{
		document.getElementById('check_'+i).checked = false;
		i++;
	}
}
//unchecks the ignore checkbox if user has typed something into one of the fields for adding new rows
function changeIgnore(area, e, u)
{
	if(area.value!="")
	{
		if(document.getElementById(e)!=undefined)
			document.getElementById(e).checked = false;
		if(document.getElementById(u)!=undefined)
			document.getElementById(u).checked = false;
	}
}
//moves fields from select menu into query textarea for SQL tab
function moveFields()
{
	var fields = document.getElementById("fieldcontainer");
	var selected = new Array();
	for(var i=0; i<fields.options.length; i++)
		if(fields.options[i].selected)
			selected.push(fields.options[i].value);
	for(var i=0; i<selected.length; i++)
		insertAtCaret("queryval", '"'+selected[i].replace(/"/g,'""')+'"');
}
//helper function for moveFields
function insertAtCaret(areaId,text)
{
	var txtarea = document.getElementById(areaId);
	var scrollPos = txtarea.scrollTop;
	var strPos = 0;
	var br = ((txtarea.selectionStart || txtarea.selectionStart == '0') ? "ff" : (document.selection ? "ie" : false ));
	if(br=="ie")
	{
		txtarea.focus();
		var range = document.selection.createRange();
		range.moveStart ('character', -txtarea.value.length);
		strPos = range.text.length;
	}
	else if(br=="ff")
		strPos = txtarea.selectionStart;

	var front = (txtarea.value).substring(0,strPos);
	var back = (txtarea.value).substring(strPos,txtarea.value.length);
	txtarea.value=front+text+back;
	strPos = strPos + text.length;
	if(br=="ie")
	{
		txtarea.focus();
		var range = document.selection.createRange();
		range.moveStart ('character', -txtarea.value.length);
		range.moveStart ('character', strPos);
		range.moveEnd ('character', 0);
		range.select();
	}
	else if(br=="ff")
	{
		txtarea.selectionStart = strPos;
		txtarea.selectionEnd = strPos;
		txtarea.focus();
	}
	txtarea.scrollTop = scrollPos;
}

function notNull(checker)
{
	document.getElementById(checker).checked = false;
}

function disableText(checker, textie)
{
	if(checker.checked)
	{
		document.getElementById(textie).value = "";
		document.getElementById(textie).disabled = true;	
	}
	else
	{
		document.getElementById(textie).disabled = false;	
	}
}

function toggleExports(val)
{
	document.getElementById("exportoptions_sql").style.display = "none";	
	document.getElementById("exportoptions_csv").style.display = "none";	
	
	document.getElementById("exportoptions_"+val).style.display = "block";	
}

function toggleImports(val)
{
	document.getElementById("importoptions_sql").style.display = "none";	
	document.getElementById("importoptions_csv").style.display = "none";	
	
	document.getElementById("importoptions_"+val).style.display = "block";	
}

function openHelp(section)
{
	PopupCenter('?help=1#'+section, "Help Section");	
}
var helpsec = false;
function PopupCenter(pageURL, title)
{
	helpsec = window.open(pageURL, title, "toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=0,width=400,height=300");
} 
function checkLike(srchField, selOpt)
{
	if(selOpt=="LIKE%"){
		var textArea = document.getElementById(srchField);
		textArea.value = "%" + textArea.value + "%";
	}
}

// Cross Origin Request
function createCORSRequest(method, url)
{
	var xhr = new XMLHttpRequest();
	if ("withCredentials" in xhr)
	{
		xhr.open(method, url, true);
	}
	else if (typeof XDomainRequest != "undefined")
	{
		xhr = new XDomainRequest();
		xhr.open(method, url);
	}
	else
	{
		xhr = null;
	}
	return xhr;
}

//check for updates
function checkVersion(installed, url)
{
	var xhr = createCORSRequest('GET', url);
	if (!xhr)
		return false;

	xhr.onload = function()
	{
		if(xhr.responseText.split("\n").indexOf(installed)==-1)
		{
			document.getElementById('oldVersion').style.display='inline';
		}
	};
	xhr.send();
}