function show_hint() {
	$('#sql').val("CREATE TABLE `users_test` (\n  `id` int(10) NOT NULL auto_increment,\n  `email` varchar(100) NOT NULL,\n  `pass` varchar(32) NOT NULL,\n  `curriculum` text NOT NULL,\n  `is_admin` int(1) NOT NULL,\n  `last_login` datetime NOT NULL,\n  `created` date NOT NULL,\n  PRIMARY KEY (`id`)\n);");
}

$(function () {
	$.each(["oneToMany", "manyToMany"], function (i, className) {
		$("a.add."+className).click(function () {
			var rows = $("div."+className);
			var clone = rows.eq(0).clone(true);
			clone.find("input").val("");
			rows.eq(rows.length - 1).after(clone);
		});
		
		$("div."+className+" a.remove").click(function () {
			var row = $(this).closest("div."+className);
			if ($("div."+className).length > 1)
				row.remove();
			else
				row.find("input").val("");
		});
	});
	
	$("form").submit(function () {
		$.each(["oneToMany", "manyToMany"], function (i, className) {
			$("div."+className).each(function (j) {
				$(this).find("input").each(function () {
					var nameAttr = $(this).attr("name");
					nameAttr = nameAttr.replace("[i]", "["+j+"]");
					$(this).attr("name", nameAttr);
				});
			})
		});
	});
});