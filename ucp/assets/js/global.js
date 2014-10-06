var SmsC = UCPMC.extend({
	init: function() {
		this.packery = false;
		this.doit = null;
		this.lastchecked = Math.round(new Date().getTime() / 1000);
	},
	prepoll: function(data) {
		var messageBoxes = { messageWindows: {}, lastchecked: this.lastchecked };
		$(".message-box[data-module=\"Sms\"]").each(function(i, v) {
			var windowid = $(this).data("id"),
					from = $(this).data("from"),
					to = $(this).data("to"),
					last = $(this).data("last-msg-id");
					messageBoxes.messageWindows[i] = { from: from, to: to, last: last, windowid: windowid };
		});
		this.lastchecked = Math.round(new Date().getTime() / 1000);
		return messageBoxes;
	},
	poll: function(data) {
		var delivered = [];
		if (data.status) {
			$.each(data.messages, function(windowid, messages) {
				$.each(messages, function(index, v) {
					if (!$(".message-box[data-id=\"" + windowid + "\"] .message[data-id=\"" + v.id + "\"]").length) {
						var Notification = new Notify(sprintf(_("New Message from %s"), v.from), {
							body: emojione.unifyUnicode(v.body),
							icon: "modules/Sms/assets/images/comment.png"
						});
						UCP.addChat("Sms", windowid, v.recp, v.did, v.recp, v.cnam, v.id, v.body);
						delivered.push(v.id);
						if (UCP.notify) {
							Notification.show();
						}
					}
				});
			});
			if (delivered.length) {
				$.post( "index.php?quietmode=1&module=sms&command=delivered", { ids: delivered }, function( data ) {});
			}
		}
	},
	display: function(event) {
		$(document).on("click", "[vm-pjax] a, a[vm-pjax]", function(event) {
			event.preventDefault(); //stop browser event
			var container = $("#dashboard-content");
			$.pjax.click(event, { container: container });
		});
		$(".message-header th[class!=\"noclick\"]").click( function() {
			var icon = $(this).children("i"),
					visible = icon.is(":visible"),
					direction = icon.hasClass("fa-chevron-down") ? "up" : "down",
					type = $(this).data("type"),
					uadd = null,
					search = (typeof $.url().param("search") !== "undefined") ? "&search=" + $.url().param("search") : "";
			if (!visible) {
				$(".cdr-header th i").addClass("hidden");
				icon.removeClass("hidden");
			}
			if (direction == "up") {
				uadd = "&order=asc&orderby=" + type + search;
				icon.removeClass("fa-chevron-down").addClass("fa-chevron-up");
			} else {
				uadd = "&order=desc&orderby=" + type + search;
				icon.removeClass("fa-chevron-up").addClass("fa-chevron-down");
			}
			$(".cdr-header th[class!=\"noclick\"]").off("click");
			$.pjax({ url: "?display=dashboard&mod=sms" + uadd, container: "#dashboard-content" });
		});
		$("i.fa-eye").click(function() {
			var id = $(this).data("id");
			$("#" + id + "-messages").toggle();
			$("#" + id + "-messages").find(".sms-message-body").each(function() { $(this).scrollTop(0); });
		});
		$("i.fa-trash-o").click(function() {
			if (confirm(_("Are you Sure you wish to delete this chat history?"))) {
				var thread = $(this).parents(".sms-message");
				$.post( "index.php?quietmode=1&module=sms&command=delete", { from: thread.data("from"), to: thread.data("to") }, function( data ) {
					if (data.status) {
						thread.fadeOut("slow");
					} else {

					}
				});
			}
		});
		$("#search-text").keypress(function(e) {
			var code = null;
			code = (e.keyCode ? e.keyCode : e.which);
			if (code == 13) {
				Sms.search($(this).val());
				e.preventDefault();
			}
		});
		$("#search-btn").click(function() {
			Sms.search($("#search-text").val());
		});
		if (typeof $.url().param("search") !== "undefined") {
			$(".sms-message-body").highlight($.url().param("search"), "yellow");
		}
	},
	search: function(text) {
		if (text !== "") {
			$.pjax({ url: "?display=dashboard&mod=sms&search=" + encodeURIComponent(text), container: "#dashboard-content" });
		} else {
			$.pjax({ url: "?display=dashboard&mod=sms", container: "#dashboard-content" });
		}
	},
	hide: function(event) {
		$(document).off("click", "[vm-pjax] a, a[vm-pjax]");
	},
	resize: function() {

	},
	initiateChat: function() {
		var to = $("#SMSto").val(), from = $("#SMSfrom").val();
		if (to !== "" && to.length <= 11 && to.length >= 10) {
			to = (to.length === 10) ? "1" + to : to;
			UCP.addChat("Sms", from + to, to, from, to);
			UCP.closeDialog();
		} else if (to.length > 11 || to.length < 10) {
			alert(_("Invalid Number"));
		}
	},
	startChat: function(from, to) {
		UCP.addChat("Sms", from + to, to, from, to);
	},
	sendMessage: function(windowId, from, to, message) {
		$(".message-box[data-id='" + windowId + "'] textarea").prop("disabled", true);
		$.post( "index.php?quietmode=1&module=sms&command=send", { from: from, to: to, message: message }, function( data ) {
			if (data.status) {
				UCP.addChatMessage(windowId, from, data.id, message, false);
				$(".message-box[data-id='" + windowId + "'] textarea").val("");
			} else {
				//TODO: Error message about sending here!
				//UCP.addChatMessage(windowId, _("System"), data.id, _("There was an error sending this message: "), false);
			}
			$(".message-box[data-id='" + windowId + "'] textarea").prop("disabled", false);
			$(".message-box[data-id='" + windowId + "'] textarea").focus();
		});
	}
}),
Sms = new SmsC();

//Logged In
$(document).bind("logIn", function( event ) {
	$("#sms-menu a.new").on("click", function() {
		$.post( "index.php?quietmode=1&module=sms&command=dids", {}, function( data ) {
			var sfrom = "";
			$.each(data.dids, function(i, v) {
				sfrom = sfrom + "<option>" + v + "</option>";
			});
			UCP.showDialog("Send Message",
				"<label for=\"SMSfrom\">From:</label> <select id=\"SMSfrom\" class=\"form-control\">" + sfrom + "</select><label for=\"SMSto\">To:</label><input class=\"form-control\" id=\"SMSto\" type='text'><button class=\"btn btn-default\" id=\"initiateSMS\" style=\"margin-left: 72px;\">Initiate</button>",
				200,
				250,
				function() {
					$("#initiateSMS").click(function() {
						Sms.initiateChat();
					});
					$("#SMSto").keypress(function(event) {
						if (event.keyCode == 13) {
							Sms.initiateChat();
						}
					});
				});
		});
	});
	$("#sms-menu a.did").on("click", function() {
		var tdid = $(this).data("did");
		$.post( "index.php?quietmode=1&module=sms&command=dids", {}, function( data ) {
			var sfrom = "";
			$.each(data.dids, function(i, v) {
				sfrom = sfrom + "<option>" + v + "</option>";
			});
			UCP.showDialog("Send Message",
				"<label for=\"SMSfrom\">From:</label> <select id=\"SMSfrom\" class=\"form-control\">" + sfrom + "</select><label for=\"SMSto\">To:</label><input class=\"form-control\" id=\"SMSto\" type='text' value='" + tdid + "'><button class=\"btn btn-default\" id=\"initiateSMS\" style=\"margin-left: 72px;\">Initiate</button>",
				200,
				250,
				function() {
					$("#initiateSMS").click(function() {
						Sms.initiateChat();
					});
					$("#SMSto").keypress(function(event) {
						if (event.keyCode == 13) {
							Sms.initiateChat();
						}
					});
				});
		});
	});
});

$(document).on("chatWindowAdded", function(event, windowId, module, object) {
	if (module == "Sms") {
		object.on("click", function() {
			object.find(".title-bar").css("background-color", "");
		});
		var from = $(".message-box[data-id=\"" + windowId + "\"]").data("from"),
		to = $(".message-box[data-id=\"" + windowId + "\"]").data("to");
		object.find("textarea").keyup(function(event) {
			if (event.keyCode == 13) {
				var message = $(this).val();
				Sms.sendMessage(windowId, from, to, message);
			}
		});
		object.find(".chat").scroll(function() {
			if ($(this)[0].scrollTop === 0) {
				var id = $(".chat .message:lt(1)").data("id");
				$(".message-box[data-id=\"" + windowId + "\"] .chat .history").prepend('<div class="message status">Loading...</div>');
				$.post( "index.php?quietmode=1&module=sms&command=history", { id: id, from: from, to: to }, function( data ) {
					$(".message-box[data-id=\"" + windowId + "\"] .chat .history .status").remove();
					var html = "";
					$.each(data.messages, function(i, v) {
						html = html + "<div class=\"message\" data-id=\"" + v.id + "\"><strong>" + v.from + ":</strong>" + v.message + "</div>";
					});
					$(".message-box[data-id=\"" + windowId + "\"] .chat .history").prepend(html);
				});
			}
		});
	}
});
