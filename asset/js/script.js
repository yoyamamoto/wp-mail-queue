// Hello javascript!
jQuery(document).ready(function(){
  let address_list = {};
  const result_container = 'div[data-id="wp_mailqueue_result"]';

  (function(){
    jQuery('[data-action="' + wp_mailqueue.add + '"]').on('click', function(e){
      if( address_list.length === 0 ) {
        alert("あて先が選択されていません");
        return false;
      }
      jQuery.ajax({
        url: wp_mailqueue.ajaxurl,
        type: "POST",
        dataType: 'json',
        cache: false,
        data: {
          nonce: wp_mailqueue.nonce,
          action: wp_mailqueue.action_add,
          post_id: jQuery(e.target).data('post_id'),
          address_list: address_list
        },
        beforeSend: function () {
          jQuery(e.target).prev('.spinner').css("visibility", "visible");
        },
        success: function (r) {
          jQuery(e.target).prev('.spinner').css("visibility", "hidden");
          let result = new Array();
          for ( let name in r.data.address_hash ) {
            result.push( '<li><b>・' + name + '(' + r.data.address_hash[name] + ')</li>' );
          }
          let msg = "";
          if ( ! r.success ) {
            msg = "エラー。予約完了できませんでした。";
          }else{
            msg += '<ul>';
            msg += result.join('');
            msg += '</ul>';
            msg += '<br>上記会場へメール送信予約が完了しました。';
          }
          jQuery(result_container).html(msg);
          refresh_queue( e.target );
        }
      });//end ajax
    });
    
    function refresh_queue( _target ){
      jQuery.ajax({
        url: wp_mailqueue.ajaxurl,
        type: "POST",
        dataType: 'json',
        cache: false,
        data: {
          nonce: wp_mailqueue.nonce,
          action: wp_mailqueue.action_get_queue,
          post_id: jQuery( _target ).data('post_id')
        },
        beforeSend: function () {
          jQuery('[data-id="wp_mailqueue_data"]').html('<tr><td colspan="3"><span class="spinner"></span></td></tr>');
          jQuery('[data-id="wp_mailqueue_data"] .spinner').css("visibility", "visible");
        },
        success: function (r) {
          jQuery('[data-id="wp_mailqueue_data"]').html(r.data.output);
        }
      });//end ajax
    }
  })();

  (function(){
    jQuery('[data-action="' + wp_mailqueue.delete + '"]').on('click', function(e){
      let $this = jQuery(this);
      let $this_tr = $this.parents('tr');
      let $find_tr = $this_tr.parent('tbody').find('tr');
      let num = Number( $this_tr.children('*:first-child').attr('rowspan') );
      let index = $find_tr.index( $this_tr.get(0) );

      jQuery.ajax({
        url: wp_mailqueue.ajaxurl,
        type: "POST",
        dataType: 'json',
        cache: false,
        data: {
          nonce: wp_mailqueue.nonce,
          action: wp_mailqueue.action_delete,
          mail_id: $this.data('mail_id')
        },
        beforeSend: function (e) {
          jQuery($this).after('<span class="spinner"></span>').next('.spinner').css("visibility", "visible");
        },
        success: function (r) {
          if ( r.success ) {
            $find_tr.slice( index, index + num ).slideUp();
          }
          jQuery($this).next('.spinner').remove();  
        }
      });//end ajax
    });
  })();

  (function(){
    let arr = [];
    jQuery('#search-form-area select, #search-form-people select, #search-form-price-low select, #search-form-price-high select').change(function(e){
      let data = jQuery(e.target).val();
      arr[jQuery(e.target).attr('name')] = data;
      jQuery.ajax({
        type: 'POST',
        dataType:'json',
        url: wp_mailqueue.ajaxurl,
        data:{
          nonce: wp_mailqueue.nonce,
          action: wp_mailqueue.action_search_post,
          area:arr["area"],
          people:arr["people"],
          price_low:arr["price_low"],
          price_high:arr["price_high"]
        },
        beforeSend: function(XMLHttpRequest){
          jQuery(result_container).html('<span class="spinner"></span>').children('.spinner').css("visibility", "visible");
        },
        success:function(r) {
          jQuery(result_container).children('.spinner').css("visibility", "hidden");
          let output = '';
          for( obj of r.data ){
            output += '<label><input type="checkbox" value="' + obj.mailto + '" data-space="' + obj.space + '" data-post-id="' + obj.id + '">' + obj.space + '（' + obj.mailto + "）</label><br>\n";
          }
          jQuery( output ).appendTo( result_container );
          jQuery( result_container + ' input:checkbox').change(function(){
            address_list = {};
            jQuery( result_container + ' input:checkbox:checked').each(function(index, element){
              address_list[ jQuery(this).data('space') ] = jQuery(this).val();
            })
          });// change
        }// success
      });// ajax
    });// onChange
  })();// local scope
});