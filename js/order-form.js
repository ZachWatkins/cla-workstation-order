(function($){

	var $form           = $('#cla_order_form');
	var admin_ajax      = typeof WSOAjax === 'undefined' ? {} : WSOAjax;
	var product_prices  = typeof cla_product_prices === 'undefined' ? {} : cla_product_prices;
	var cost_allocation = typeof cla_allocation === 'undefined' ? '' : cla_allocation;
	var cost_threshold  = typeof cla_threshold === 'undefined' ? '' : cla_threshold;
	var is_order        = typeof cla_is_order === 'undefined' ? '' : cla_is_order;
	var post_status     = typeof cla_status === 'undefined' ? '' : cla_status;

	// Remove product from shopping cart.
	var removeProduct = function(e){

		// Prevent default behavior on click.
		e.preventDefault();

		// Get variables used to update the shopping cart.
		var productID = this.getAttribute('data-product-id');
		// Remove product ID from form field.
		var $productIDsField = $('#cla_product_ids');
		var newProductIDs = $productIDsField.val().replace( productID, '' ).replace( /,+/g, ',' ).replace( /^,|,$/, '' );
		$productIDsField.val(newProductIDs);

		// Remove the shopping cart element.
		$('.shopping-cart-'+productID).remove();

		// Enable the Add to Cart button.
		$('#cart-btn-'+productID).removeAttr('disabled').html('Add');

		// Update total purchase price.
		updateTotals();

	};

	// Add product to shopping cart.
	var addProductToCart = function(e){

		// Get elements and values.
		var $this = $(this);
		var productID = $this.attr('data-product-id');
		var productName = $('#product-' + productID + ' .card-header').html();
		var productPrice = formatDollars( product_prices[productID] );
		var $thumb = $('#product-'+productID+'.card .wp-post-image');

		// Add product ID to form field.
		var $productIDsField = $('#cla_product_ids');
		var products         = $productIDsField.val();
		if ( products.indexOf(',') >= 0 ) {
			products = products.split(',');
		} else if ( '' !== products ) {
			products = [products];
		} else {
			products = [];
		}
		products.push(productID);
		var newProductIDs    = products.join(',');
		$productIDsField.val( newProductIDs );

		// Generate HTML elements for shopping cart listing.
		var listItem = '<div class="cart-item shopping-cart-'+productID+' grid-x">';
		if ( $thumb.length > 0 ) {
			listItem += '<div class="cell shrink"><img width="50" src="'+$thumb.attr('src')+'"></div>';
		}
		listItem += '<div class="cell auto">'+productName+'</div>';
		listItem += '<div class="cell shrink align-right bold"><button class="trash trash-product" type="button" data-product-id="'+productID+'">Remove product from cart</button>'+productPrice+'</div>';
		listItem += '</div>';

		// Append item.
		$('#list_purchases').append(listItem);

		// Add event handlers
		// $('#list_purchases').find('.shopping-cart-'+productID+' .trash').on('click', removeProduct);

		// Disable button.
		this.setAttribute('disabled','disabled');
		this.innerHTML = 'In cart';

		// Update total purchase price.
		updateTotals();

		// Prevent default behavior on click.
		// e.preventDefault();
		// return false;

	};

	// Get total cost of products.
	var getTotal = function(){

		// Get array of post IDs for product post type.
		var $productIDsField = $('#cla_product_ids');
		var ids = $productIDsField.val();
				ids = ids.replace(/^,|,$/g,'');

		if ( ids.indexOf(',') >= 0 ) {
			ids = ids.split(',');
		} else if ( ids !== '' ) {
			ids = [ids];
		} else {
			ids = [];
		}

		// Add price associated with each product to total.
		var total = 0;
		for ( var i=0; i < ids.length; i++ ) {
			var id = ids[i];
			var price = parseFloat( product_prices[id] );
			total = Number((total + price).toFixed(2));
		}

		// Get quote items total.
		var $quotePrices = $form.find('.products-custom-quote .cla-quote-price');
		$quotePrices.each(function(){

			if ( this.value === '' || this.value === '.' ) {
				var price = 0;
			} else {
				var price = parseFloat( this.value.replace(/\$|,/g, '') );
			}
			total = Number((total + price).toFixed(2));
		});
		return total;

	};

	var validateContribution = function(){

		// Get numbers involved.
		var total = getTotal();
		var contributionMade = '' === $('#cla_contribution_amount').val() ? 0 : parseFloat( $('#cla_contribution_amount').val() );
		var returnVal = {
			needsContribution: false,
			difference: 0
		};

		// Determine if change in contribution needed.
		if ( cost_threshold < total ) {
			returnVal.needsContribution = true;
			returnVal.difference = Number((total - cost_allocation - contributionMade).toFixed(2));
		} else {
			returnVal.difference = 0 - contributionMade;
		}

		return returnVal;

	};

	// Update total purchase cost.
	var updateTotals = function(){

		// Get total cost of products.
		var total = getTotal();

		// Convert total to string and push to DOM elements.
		var totalString = formatDollars(total);
		$('#products_total').html(totalString);

		// Check for contribution needed.
		var $allocationData = $('#allocation-data');
		var $contributionNeededEl = $('#contribution_needed');
		var contribution = validateContribution();

		// Show or hide the contribution amount needed and disable the form.
		if ( contribution.difference === 0 ) {
			$allocationData.addClass('hidden');
			$('#contribution_needed_label').html('Contribution needed');
			$contributionNeededEl.html('$0.00');
		} else {
			$allocationData.removeClass('hidden');
			$contributionNeededEl.html( formatDollars( Math.abs( contribution.difference ) ) );
			if ( contribution.difference > 0 ) {
				// Underpaid.
				$('#contribution_needed_label').html('Contribution needed');
			} else {
				// Overpaid.
				$('#contribution_needed_label').html('Excess contribution');
			}
		}

	};

	var removeQuoteFieldset = function(){

		// Remove this item from the DOM.
		var $this = $(this);
		var index = $this.attr('data-quote-index');
		var $item = $form.find('.cla-quote-item[data-quote-index="'+index+'"]');
		var $cartItem = $form.find('.cart-item.quote-item-'+index);
		$item.remove();
		$cartItem.remove();

		// Update all indexes on existing elements in the form fields.
		$form.find('.cla-quote-item').each(function( index ){

			var $this = $(this);
			$this.attr('data-quote-index', index);

			$this.find('label[for="cla_quote_"]').each(function(){
				this.for = this.for.replace(/\d+/, index);
			});

			$this.find('input[name="cla_quote_"],textarea[name="cla_quote_"]').each(function(){
				var newid = this.id.replace(/\d+/, index);
				this.id = newid;
				this.name = newid;
			});

		});

		// Update all indexes on existing elements in the shopping cart.
		$form.find('#list_purchases .quote-item').each(function( index ){
			this.className = this.className.replace( /quote-item-\d+/, 'quote-item-'+index );
			$(this).find('.trash').attr( 'data-quote-id', index );
		});

		// Decrement quote counter.
		var count = parseInt( $form.find('#cla_quote_count').val() );
		$form.find('#cla_quote_count').val( count - 1 );

		// Update totals.
		updateTotals();

	};

	var formatDollars = function( value ) {

		if ( typeof value === 'string' ) {
			value = parseFloat( value.replace(/\$|,/g,'') );
		}

		if ( value.length === 0 ) {
			value = 0;
		}

		value = '$' + value.toFixed(2).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");

		return value;

	};

	var updateQuoteCartItem = function(e) {

		var $this = $(this); // The price form field.
		var index = parseInt( $this.parents('.cla-quote-item').attr('data-quote-index') );
		var $cartItem = $( '#list_purchases .quote-item-' + index );
		var price = $this.val();
				price = formatDollars( price );
		$cartItem.find('.price').html( price );

	}

	var addQuoteFieldset = function(){

		// Create HTML.
		var newIndex = $form.find('.cla-quote-item').length;
		var html = '<div class="cla-quote-item grid-x grid-margin-x" data-quote-index="'+newIndex+'">';
				html += '<div class="cell small-12 medium-4"><label for="cla_quote_'+newIndex+'_name">Name</label><input name="cla_quote_'+newIndex+'_name" id="cla_quote_'+newIndex+'_name" class="cla-quote-name" type="text" />';
				html += '<label for="cla_quote_'+newIndex+'_price">Price</label><input name="cla_quote_'+newIndex+'_price" id="cla_quote_'+newIndex+'_price" class="cla-quote-price" type="number" min="0" step="0.01" /></div>';
				html += '<div class="cell small-12 medium-4"><label for="cla_quote_'+newIndex+'_description">Description</label><textarea name="cla_quote_'+newIndex+'_description" id="cla_quote_'+newIndex+'_description" class="cla-quote-description" name="cla_quote_'+newIndex+'_description"></textarea></div>'
				html += '<div class="cell small-12 medium-auto"><label for="cla_quote_'+newIndex+'_file">File</label><input name="cla_quote_'+newIndex+'_file" id="cla_quote_'+newIndex+'_file" class="cla-quote-file" type="file" accept=".pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"/></div>';
				html += '<div class="cell small-12 medium-shrink"><button type="button" class="remove" data-quote-index="'+newIndex+'">Remove this quote item</button></div>';
				html += '</div>';

		// Add to page.
		$form.find('.products-custom-quote #list_quotes').append(html);

		// Add event handlers.
		$item = $form.find('.cla-quote-item[data-quote-index="'+newIndex+'"]');
		$item.find('.remove').on('click', removeQuoteFieldset);
		$item.find('.cla-quote-price').on('keyup', updateQuoteCartItem);
		$item.find('.cla-quote-price').on('keyup', updateTotals);

		// Add element to shopping cart.
		// Get elements and values.
		var productName = 'Advanced Teaching/Research Item';
		var strProductPrice = $item.find('.cla-quote-price').val();
		if ( strProductPrice.length === 0 || strProductPrice === '.' ) {
			strProductPrice = '$0.00';
		} else {
			var intProductPrice = parseFloat( strProductPrice.replace( /\$|,/g, '' ) );
			strProductPrice = '$' + intProductPrice.toFixed(2).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
		}

		// Generate HTML elements for shopping cart listing.
		var listItem = '<div class="cart-item quote-item quote-item-'+newIndex+' grid-x">';
				listItem += '<div class="cell auto">'+productName+'</div>';
				listItem += '<div class="cell shrink align-right bold"><button class="trash trash-quote" type="button" data-quote-index="'+newIndex+'">Remove product from cart</button><span class="price">'+strProductPrice+'</span></div>';
				listItem += '</div>';

		// Append item.
		$('#list_purchases').append(listItem);

		// Add event handlers
		// $('#list_purchases').find('.quote-item-'+newIndex+' .trash').on('click', removeQuoteFieldset);

		// Increment quote counter.
		var count = parseInt( $form.find('#cla_quote_count').val() );
		$form.find('#cla_quote_count').val( count + 1 );

		// Update total purchase price.
		updateTotals();

	};

	var validateForm = function(e){

		var valid = true;
		var message = '';

		// Remove flags.
		$form.find('.flagged').removeClass('flagged');

		// IT Representative
		$itRep = $form.find('#cla_it_rep_id');
		if ( $itRep.val() === '-1' ) {
			valid = false;
			$form.find('label[for="cla_it_rep_id"]').addClass('flagged');
			message += '<li>Please choose an IT Representative.</li>';
		}

		// Building name.
		$buildingName = $form.find('#cla_building_name');
		if ( $buildingName.val() === '' ) {
			valid = false;
			$form.find('label[for="cla_building_name"]').addClass('flagged');
			message += '<li>Please provide a building name.</li>';
		}

		// Room number.
		$roomNumber = $form.find('#cla_room_number');
		if ( $roomNumber.val() === '' ) {
			valid = false;
			$form.find('label[for="cla_room_number"]').addClass('flagged');
			message += '<li>Please provide a room number.</li>';
		}

		// Current workstation.
		$assetNumber = $form.find('#cla_current_asset_number');
		$noComputer = $form.find('#cla_no_computer_yet');
		if ( $assetNumber.val() === '' ) {
			if ( ! $noComputer.is(':checked') ) {
				valid = false;
				$form.find('label[for="cla_current_asset_number"]').addClass('flagged');
				message += '<li>Please provide an asset number.</li>';
			}
		} else if ( $noComputer.is(':checked') ) {
			valid = false;
			$form.find('label[for="cla_current_asset_number"]').addClass('flagged');
			message += '<li>Please either uncheck "I don\'t have a computer yet" or clear the field labeled "Current Workstation Asset Number".</li>';
		}

		// Account Number.
		var $accountNumber = $form.find('#cla_account_number');
		var contributionAmount = parseFloat( $form.find('#cla_contribution_amount').val() );
		var contribution = validateContribution();
		if ( contribution.needsContribution === true && $accountNumber.val() === '' ) {
			// Contribution and account number don't match up.
			valid = false;
			$form.find('label[for="cla_account_number"]').addClass('flagged');
			message += '<li>Please provide a contribution account.</li>';
		}
		if ( contribution.difference > 0 ) {
			// Contribution still needed.
			valid = false;
			$form.find('label[for="cla_contribution_amount"]').addClass('flagged');
			message += '<li>You must contribute more funds.</li>';
		} else if ( contribution.difference < 0 ) {
			valid = false;
			$form.find('label[for="cla_contribution_amount"]').addClass('flagged');
			message += '<li>You must reduce your funds contribution.</li>';
		}

		// Products purchased.
		$products = $form.find('#list_purchases .cart-item');
		if ( $products.length === 0 ) {
			valid = false;
			$form.find('#products .toggle .btn').addClass('flagged');
			message += '<li>Please select one or more products.</li>';
		}

		// Quote Items.
		$form.find('.cla-quote-price,.cla-quote-name,.cla-quote-description,.cla-quote-file').each(function(){
			if ( this.value.length === 0 ) {
				valid = false;
				$(this).parent().find('label[for="' + this.id + '"]').addClass('flagged');
				var label = $(this).parent().find('label[for="' + this.id + '"]').html().toLowerCase();
				message += '<li>Please provide a ' + label + ' for your custom quote.</li>';
			}
		});

		// Validate custom quote file selection.
		$form.find('.cla-quote-file').each(function(){
			// Validate file extension ('pdf', 'doc', 'docx').
			var filename  = this.value;
			if ( '' !== filename ) {
				var extension = filename.match(/(pdf|doc|docx)$/g)[0];
				if ( extension !== 'pdf' && extension !== 'doc' && extension !== 'docx' ) {
					valid = false;
					$(this).parent().find('label[for="' + this.id + '"]').addClass('flagged');
					message += '<li>Please provide a custom quote file in pdf, doc, or docx format.</li>';
				}
				// Validate file size (1024000).
				var files = $(this).prop('files');
				var size = files[0].size;
				if ( size > 1024000 ) {
					valid = false;
					$(this).parent().find('label[for="' + this.id + '"]').addClass('flagged');
					message += '<li>Custom quote file size must be less than or equal to 1mb.</li>';
				}
			}
		});

		// Enable or disable the submit button.
		if ( ! valid ) {
			if ( typeof e === 'event' ) {
				e.preventDefault();
			}
		}

		if ( message !== '' ) {
			message = '<ul>' + message + '</ul>';
		}

		return {status: valid, message: message};

	};

	var resetForm = function(){
		// Empty text fields.
		$form
			.find('input[type="text"], input[type="file"], input[type="hidden"]:not(#the_superfluous_nonceity_n8me), textarea')
			.val('');
		// Remove HTML elements representing products.
		$form
			.find('.cla-quote-item, #list-purchases .cart-item')
			.remove();
		// Uncheck checkboxes.
		$form
			.find('input[type="checkbox"]:checked')
			.prop('checked',false);
		// Reset select elements.
		$form
			.find('#cla_it_rep_id')[0]
			.selectedIndex = 0;
	};

	// Add event handlers
	$('#list_purchases').on( 'click', '.trash-product', removeProduct );
	$('#list_purchases').on( 'click', '.trash-quote', removeQuoteFieldset );
	$('#list_quotes').on( 'click', '.remove', removeQuoteFieldset );
	$('.add-product').on('click', addProductToCart);
	$('#cla_contribution_amount').on('keyup', updateTotals);
	$form.find('#cla_add_quote').on('click', addQuoteFieldset);
	// Add submit event handler.
	jQuery('#cla_order_form').submit(ajaxSubmit);
	function ajaxSubmit(e) {
    e.preventDefault();
 		var validation = validateForm();
 		if ( validation.status === true ) {
 			$form.find('#order-message').html('');
      var form_data = new FormData();
      $form.find('input:not([type="checkbox"]):not([type="file"]),select,textarea').each(function(){
      	form_data.append(this.name, this.value);
      });
      $form.find('input[type="checkbox"]').each(function(){
      	var value = this.checked ? 'on' : 'off';
      	form_data.append(this.name, value);
      });
      $form.find('input[type="file"]').each(function(){
      	form_data.append(this.name, this.files[0]);
      });
      form_data.append('action', 'make_order');
      form_data.append('_ajax_nonce', admin_ajax.nonce);
	    jQuery.ajax({
	      type: "POST",
	      url: admin_ajax.ajaxurl,
				contentType: false,
				processData: false,
	      data: form_data,
	      success: function(data) {
      		$form.find('#order-message').html('');
	      	if ( data.indexOf('{') === 0 ) {
						// Only JSON returned.
	      		var response = JSON.parse(data);
	      		if ( response.errors.length > 0 ) {
	      			var list = '';
	      			for ( var i = 0; i < response.errors.length; i++ ) {
	      				list += '<li>' + response.errors[i] + '</li>';
	      			}
	      			if ( list !== '' ) {
	      				list = '<ul>' + list + '</ul>';
	      			}
	      			$form.find('#order-message').append(list);
	      		} else if ( is_order && 'returned' !== post_status ) {
	        		$form.find('#order-message').append('<div class="fade-out">Order updated.</div>');
	        		window.setTimeout(function(){
	        			$form.find('#order-message .fade-out').fadeOut();
	        		}, 3000);
		      	} else {
		      		// Clear form.
		      		$formParent = $form.parent();
		      		$formParent.html('<div class="confirmation-message"><p>Your order was submitted: <a href="' + response.order_url + '">' + response.order_url + '</a></p><p>We will notify you via email when there are updates to your order.</p></div>');
		      	}
	      	} else {
	      		$form.find('#order-message').html('There was an error submitting the order: ' + data);
	      	}
	      },
	      error: function( jqXHR, textStatus, errorThrown ) {
	      	$form.find('#order-message').html('The application encountered an error while submitting your request (' + errorThrown + ').<br>' + jqXHR.responseText );
	      }
	    });
 		} else {
			// Show error message.
			$form.find('#order-message').html('There was a problem with your order. Please look for any errors below.<br>' + validation.message);
 		}
    return false;
  }

})(jQuery);

// Provide toggle feature for product categories.
(function($){

	var toggleActive = function(e) {

		// Prevent default.
		e.preventDefault();

		$(this).parent().parent().toggleClass('active');

	}

	// Attach event handlers.
	$('#products .toggle .btn').on('click', toggleActive);

})(jQuery);

// Provide toggle feature for product details.
(function($){

	var toggleActive = function(e) {

		// Prevent default.
		e.preventDefault();

		// Toggle "active" class name on parent.
		var element = $(this).parent().toggleClass('active');

	}

	// Attach event handlers.
	$('#products .more-details').on('click', toggleActive);

})(jQuery);
