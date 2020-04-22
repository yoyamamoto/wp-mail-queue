<?php

namespace Src;
use Src\Mail_Queue_Model;
use Src\Setting;
use Src\Pattern\Singleton;

class Mail_Queue extends Singleton {
  private $setting;

	public function __construct(){
    $this->setting = Setting::get_instance();
    add_action( 'init', array( $this, 'create_post_type' ) );
    add_action( 'admin_notices', array( $this, 'notice' ) );
    add_action( 'add_meta_boxes_wp_mailqueue', array( $this, 'wp_mailqueue_meta_box' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_script_func' ) );
    add_action( 'wp_ajax_' . 'wp_mailqueue_action', array( $this, 'ajax_wp_mailqueue' ) );
    add_action( 'wp_ajax_' . 'wp_search_post_action', array( $this, 'ajax_wp_search_post' ) );
    add_action( 'wp_ajax_' . 'wp_get_queue_action', array( $this, 'ajax_wp_get_queue' ) );
    add_action( 'wp_ajax_' . 'wp_delete_queue_action', array( $this, 'ajax_wp_delete_queue' ) );
    
    //register_activation_hook( WMQ_FILE, array( $this, 'activate_options' ) );
    //register_deactivation_hook( WMQ_FILE, array( $this, 'deactive_options' ) );
    
	  //register_uninstall_hook( WMQ_FILE, array( __class__, 'delete_options' ) );
  }
  
	public function activate_options(){
    $this->create_table_queue();
    $this->create_table_mail();
	}

	public function deactive_options(){
    //delete_option( $this->option_name );
    $this->delete_table_queue();
    $this->delete_table_mail();
	}


	// Notices
	public function notice(){
		global $hook_suffix;
    global $pagenow;
    if( ! current_user_can( 'manage_options') ) return;
    if ( $pagenow == 'index.php' ) {
    }

    $html  = '<div class="updated">';
    $html .= '  <p>一斉メール送信機能の稼働中です。左側メニューよりメール内容を作成し保存⇒会場を選択してメール送信予約をするとメール送信予約されます。10分おきに10通のメールが送信される設定になっています。送信元メールアドレスはregistration@partylabel.netになっております。</p>';
    $html .= '  <p>メールの署名や設定に関しては、左側メニューの設定→一斉メール送信の設定から行えます。</p>';
    $html .= '</div>';    
    echo $html;
	}


  /**
   * 送信メール キュー用のポストタイプを作成する
   */
  public function create_post_type() {
    register_post_type( 'wp_mailqueue',
      array(
        'labels' => array(
          'name'				=> '一斉メール送信',
          'add_new'			=> '新たに送信するメールを登録する',
          'add_new_item'		=> '一斉メール送信の新規登録',
          'edit_item'			=> 'メール内容を編集',
          'new_item'			=> '送信するメール内容を新規追加',
          'view_item'			=> 'メール送信情報を見る',
          'search_items'		=> 'メール送信履歴を探す',
          'not_found'			=> 'メール送信履歴はありません',
          'not_found_in_trash'=> 'ゴミ箱にメール送信履歴はありません'
        ),
        'description'			=> 'メールアドレスが登録されている契約会場の全てへ一斉にメールを送信します。',
        'public'				=> false,
        'publicly_queryable'	=> false,
        'show_ui'				=> true,
        'query_var'			=> false,
        'capability_type'		=> 'post',
        'hierarchical'		=> false,
        'menu_position'		=> 10,
        'supports'			=> array('title','editor'/*, 'excerpt'*/),
        'menu_icon'     => 'dashicons-email',
      )
    );
  }

  /**
   * 管理画面にメタボックスを生成
   */
  public function wp_mailqueue_meta_box(){
    add_meta_box(
      'meta_box_wp_mailqueue_sendmail',
      '会場へメールを一斉送信',
      array($this, 'meta_box_wp_mailqueue_sendmail_callback'),
      'wp_mailqueue',
      'side',
      'low'
    );
  }

  /**
   * メタボックスのコールバック関数
   */
  public function meta_box_wp_mailqueue_sendmail_callback( $post ){
    ?>
    <style type="text/css">
      #wp_mailqueue_searchform {
        margin:15px 0;
      }
      #wp_mailqueue_result {
        border:1px dotted #ccc;
        margin:15px 0;
        padding:10px;
        overflow:hidden;
        position:relative;
      }
      #wp_mailqueue_result .spinner{
        float:none;
        position:absolute;
        top:0px;
        left:5px;
        margin:0;
      }
      #wp_mailqueue_action .spinner {
        float: left;
      }
      #wp_mailqueue_content th {
        text-align:left;
      }
      #wp_mailqueue_table {
        border-spacing:0;
        border-collapse: collapse;
      }
      #wp_mailqueue_table th,
      #wp_mailqueue_table td {
        border:1px solid #ccc;
        padding:.3em;
      }
    </style>
    <div id="wp_mailqueue_action">
      <button type="button" class="button button-primary button-large" data-action="wp_mailqueue_sendmail_btn" data-post_id="<?php echo $post->ID; ?>">
        一斉メールを送信予約する
      </button>
      <div class="clear"></div>
    </div>

    <div id="wp_mailqueue_searchform">
      <?php $this->get_the_searchform(); ?>
    </div>

    <div id="wp_mailqueue_result" data-id="wp_mailqueue_result">
      <span class="spinner"></span>
    </div>

    <h3>予約済み送信先一覧</h3>
    <table id="wp_mailqueue_table">
      <thead>
      <tr><th>メール予約情報</th><th>宛先</th><th>状態</th></tr>
      </thead>
      <tbody data-id="wp_mailqueue_data">
      <?php echo $this->get_queue( array( 'post_id' => $post->ID ) ); ?>
      </tbody>
    </table>
    <?php
  }

  /**
   * 送信予約されたキューデータを取得
   */
  private function get_queue( $arg = array( 'post_id' => 0) ){
    $output = '';
    $mq_mail_list = $this->get_mq_mail_data( array( 'post_id' => $arg['post_id'] ) );
    foreach( $mq_mail_list as $mq_mail ):
      // 送信完了 確認フラグ
      $mail_complete = true;
      // キューを取得
      $mq_queue_list = $this->get_mq_queue_data(array(
        'mail_id' => $mq_mail->id
      ));
      $queue_count = count( $mq_queue_list );
      // キューの送信完了チェック
      foreach( $mq_queue_list as $mq_queue ):
        if( $mail_complete === true && $mq_queue->status === '0' ){
          $mail_complete = false;
        }
        $output .= '<tr>';
        if( $mq_queue === reset( $mq_queue_list ) ){
          $output .= '<td rowspan="' . $queue_count . '">';
          $output .= '処理完了日：' . $mq_mail->send_date . '<br>';
          $output .= '送信予約日：' . $mq_mail->estimated_date . '<br>';
          $output .= '<div style="text-align:center;"><button type="button" class="button" data-action="wp_mailqueue_delete_btn" data-mail_id="' . $mq_mail->id . '">削除</button></div>';
          $output .= '</td>';
        }
        $output .= '<td><b>' . $mq_queue->mail_to_name . '</b>&lt;' . $mq_queue->address . '&gt</td>';
        $output .= '<td>';
        switch ( $mq_queue->status ) {
          case '0':
            $output .= '未送信';
            break;
          case '1':
            $output .= '完了';
            break;
          case '-1':
            $output .= '送信エラー';
            break;
        }
        $output .= '</td>';
        $output .= '</tr>';
      endforeach;
      if( $mail_complete === true && $mq_mail->status === '0' ){
        $this->update_mail(
          $mq_mail->id,
          array(
            'status' => 1,
            'send_date' => current_time( 'mysql' )
          )
        );
      }
    endforeach;
    
    /*
    d($mq_mail->queue_ids);
    d($mq_mail->status);
    d($mq_mail->error_ids);
    d($mq_mail->send_date);
    d($mq_mail->estimated_date);
    */
    return $output;
  }

  public function ajax_wp_get_queue(){
    try {
      if( ! check_ajax_referer('wp_mailqueue', 'nonce', false) ){
        throw new Exception('不明なエラーが発生しています。');
      }
      $post_id = $this->get_post_data( 'post_id' );
      $data = array();
      $data['output'] = $this->get_queue( array( 'post_id' => $post_id ) );
      wp_send_json_success( $data );
    } catch (Exception $e) {
      wp_send_json_error( array( 'msg' => 'ERROR: ' . $e->getMessage() . "\n" ) );
    }
  }

  /**
   * サーチフォームを取得する
   */
  private function get_the_searchform(){
  ?>
    <table>
      <tr>
        <td>
          <label id="search-form-area" class="search-form-select-label">
            <select name="area">
              <option value="">全エリア</option>
              <?php
                if( is_category() ){
                  $cat = get_queried_object();
                  $area = $cat->slug;
                }else {
                  $area = get_query_var( 'area' );
                }
                wp_nav_menu(array(
                  'menu' => 'サイドバー - ページ - カテゴリ',
                  'container' => false,
                  'items_wrap' => '%3$s',
                  'walker' => new \Custom_Mailqueue_Walker_Nav_Menu(array(
                      'cat_slug' => $area,
                      'output' => 'option'
                  ))
                ));
              ?>
            </select>
          </label>
        </td>
        <td>
          <label id="search-form-people" class="search-form-select-label">
            <select name="people">
              <option value="">人数</option>
              <?php
                $people = get_query_var( 'people', '');
                $option_list = array(20, 30, 40, 50, 60, 70, 80, 90, 100, 120, 150, 200, 300);
                foreach( $option_list as $option ):
              ?>
              <option value="<?php echo $option; ?>"<?php echo ( $people == $option ) ? ' selected="selected"':'';?>><?php echo $option; ?> 人</option>
              <?php
                endforeach;
              ?>
            </select>
          </label>
        </td>
        <td>
          <label id="search-form-price-low" class="search-form-select-label">
            <select name="price_low">
              <option value="">価格を選択</option>
              <?php
                $price_low = get_query_var( 'price_low', '');
                $option_list = array(3000, 3500, 4000, 4500, 5000, 5500, 6000, 7000, 8000);
                foreach( $option_list as $option ):
              ?>
              <option value="<?php echo $option; ?>"<?php echo ($price_low == $option) ? ' selected="selected"':'';?>><?php echo $option; ?> 円</option>
              <?php
                endforeach;
              ?>
            </select>
          </label>
          <span id="search-form-price-between">～</span>
          <label id="search-form-price-high" class="search-form-select-label">
            <select name="price_high">
              <option value="">価格を選択</option>
              <?php
                $price_high = get_query_var( 'price_high', '' );
                $option_list = array(3500, 4000, 4500, 5000, 5500, 6000, 7000, 8000, 10000);
                foreach( $option_list as $option ):
              ?>
              <option value="<?php echo $option; ?>"<?php echo ($price_high == $option) ? ' selected="selected"':'';?>><?php echo $option; ?> 円</option>
              <?php
                endforeach;
              ?>
            </select>
          </label>
        </td>
      </tr>
    </table>
  <?php
  }

  /*
   * スクリプトを登録する
   */
  public function admin_enqueue_script_func() {
    wp_enqueue_script( 'wp_mailqueue', WMQ_URL . 'asset/js/script.js', array( 'jquery' ) );
    wp_localize_script( 'wp_mailqueue', 'wp_mailqueue', array(
      'ajaxurl' => admin_url( 'admin-ajax.php' ),
      'action_add' => 'wp_mailqueue_action',
      'action_search_post' => 'wp_search_post_action',
      'action_get_queue' => 'wp_get_queue_action',
      'action_delete' => 'wp_delete_queue_action',
      'nonce' => wp_create_nonce( 'wp_mailqueue' ),
      'add' => 'wp_mailqueue_sendmail_btn',
      'delete' => 'wp_mailqueue_delete_btn'
    ) );
  }
  
  /**
   * 送信先 絞り込み ajax コールバック
   */
	public function ajax_wp_search_post() {
    try {
      if( ! check_ajax_referer('wp_mailqueue', 'nonce', false) ){
        throw new Exception('不明なエラーが発生しています。');
      }

      $data = array();
      $data['area'] = $this->get_post_data( 'area' );
      $data['people'] = $this->get_post_data( 'people' );
      $data['price_low'] = $this->get_post_data( 'price_low' );
      $data['price_high'] = $this->get_post_data( 'price_high' );
      if( ! empty( $data['area'] ) )	{
        $cat_id = get_category_by_slug( $data['area'] )->cat_ID;
      }
      $meta_query = array( array( 'key' => 'cftantou', 'compare' => 'EXISTS' ) );

      if( ! empty( $data['people'] ) && preg_match("/\d+/", $data['people']))	{
        $meta_query[] = array('key'=>'cfminpeoplestand',	'value'=> $data['people'],	'compare'=>'<=',	'type'=>'NUMERIC');
        $meta_query[] = array('key'=>'cfmaxpeoplestand',	'value'=> $data['people'],	'compare'=>'>=',	'type'=>'NUMERIC');
      }
      if( ! empty( $data['price_low'] ) && preg_match("/\d+/", $data['price_low']))	{
        $meta_query[] = array('key'=>'cffbprice',	'value'=> $data['price_low'] ,	'compare'=>'>=',	'type'=>'NUMERIC');
      }
      if( ! empty( $data['price_high'] ) && preg_match("/\d+/", $data['price_high']))	{
        $meta_query[] = array('key'=>'cffbprice',	'value'=> $data['price_high'] ,	'compare'=>'<=',	'type'=>'NUMERIC');
      }

      if( count( $meta_query )>2 )	{ $meta_query['relation'] = 'AND'; }

      $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page'   => -1,
        'category__not_in' => array(1)
      );
      
      if( ! empty( $cat_id ) ){ $args = array_merge($args, array('cat' => $cat_id)); }
      if( ! empty( $meta_query ) ){ $args = array_merge($args, array('meta_query' => $meta_query)); }
      $output = array();
      $posts =  get_posts( $args );
      foreach( $posts as $post ){
        $output[] = array(
          'id' => $post->ID,
          'mailto' => get_post_meta( $post->ID, 'cftantou', true ),
          'space' => $post->post_title
        );
      }
      wp_send_json_success( $output );
    } catch (Exception $e) {
      wp_send_json_error( array( 'msg' => 'ERROR: ' . $e->getMessage() . "\n" ) );
    }
  }

  /**
   * メールキュー追加 ajax callback
   */
	public function ajax_wp_mailqueue() {
    try {
      if( ! check_ajax_referer('wp_mailqueue', 'nonce', false) ){
        throw new Exception('不明なエラーが発生しています。');
      }

      $data = array();
      $data['post_id'] = $this->get_post_data( 'post_id' );
      if( $data['post_id'] === false ) {
        throw new Exception('投稿IDが不明です');
      }

      // 宛先を追加
      $address_hash = filter_input(
        INPUT_POST,
        'address_list',
        FILTER_DEFAULT,
        FILTER_REQUIRE_ARRAY
      );

      $data['address_hash'] = $this->address_hash_arrange( 
        $this->revers_key_values( $address_hash )
      );

      /*
        $data['address_hash']
        'あて先名': "mailaddress01@example.com,mailaddress02@example.com",
        'あて先名１、あて先名２': "mailaddress02@example.com"
      */

      if( empty( $data['address_hash'] ) ) {
        throw new Exception('あて先が不明です');
      }
      
      // メールコンテンツキューに追加
      $data['mail_id'] = $this->add_mail( $data['post_id'] );
      if( $data['mail_id'] === false ) {
        throw new Exception('メールコンテンツキューに追加できませんでした。');
      }

      // メールアドレス取得元の投稿IDを入れる
      $mail_to_post_id = 0;

      // 送信キューに追加
      $queue_ids_arr = array();
       foreach( $data['address_hash'] as $mail_to_name => $address ){
        $queue_ids_arr[] = $this->add_queue( $data['mail_id'], $address, $mail_to_name );
      }
      if( empty( $queue_ids_arr ) ){
        throw new Exception('送信キューに追加できませんでした。');
      }
      wp_send_json_success( $data );
    } catch (Exception $e) {
      wp_send_json_error( array( 'msg' => 'ERROR: ' . $e->getMessage() . "\n" ) );
    }
  }

  public function ajax_wp_delete_queue(){
    try {
      if( ! check_ajax_referer('wp_mailqueue', 'nonce', false) ){
        throw new Exception('不明なエラーが発生しています。');
      }
      $mail_id = $this->get_post_data( 'mail_id' );

      //削除処理
      $return_mail = $this->delete_row_mail(array(
        'where' => array(
          'id' => $mail_id
        ),
        'format' => '%d'
      ));
      $return_queue = $this->delete_row_queue(array(
        'where' => array(
          'mail_id' => $mail_id
        ),
        'format' => '%d'
      ));
      $message = '';
      $message .= ( $return_mail === false ) ? 'コンテンツキューの削除に失敗しました。':'';
      $message .= ( $return_queue === false ) ? '送信キューの削除に失敗しました。':'';
      if( !empty( $message ) ){
        throw new Exception( $message );
      }
      wp_send_json_success();
    } catch (Exception $e) {
      wp_send_json_error( array( 'msg' => 'ERROR: ' . $e->getMessage() . "\n" ) );
    }
  }

  public function get_post_data( $label ){
    return filter_input( INPUT_POST, $label );
  }

  /**
   * add mq_queue record
   */
  private function add_queue( $mail_id, $address, $mail_to_name ){
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'mq_queue',
      array(
        'mail_id'         => $mail_id,
        'status'          => 0,
        'estimated_date'  => current_time( 'mysql' ),
        'address'         => $address,
        'mail_to_name' => $mail_to_name,
      ),
      array(
        '%d',
        '%d',
        '%s',
        '%s',
        '%s'
      )
    );
    return ( $result !== false ) ? $wpdb->insert_id : false;
  }

  /**
   * add mq_mail record
   */
  private function add_mail( $post_id ){
    global $wpdb;
    $result = $wpdb->insert(
      $wpdb->prefix . 'mq_mail',
      array(
        'post_id'          => $post_id,
        'status'           => 0,
        'estimated_date'   => current_time( 'mysql' )
      ),
      array(
        '%d',
        '%d',
        '%s'
      )
    );
    return ( $result !== false ) ? $wpdb->insert_id : false;
  }

  /**
   * Update mq_mail record
   */
  private function update_mail( $id, $data = array(), $data_type = null ){
    global $wpdb;
    $result = $wpdb->update(
      $wpdb->prefix . 'mq_mail',
      $data,
      array( 'ID' => $id ),
      $data_type,
      array( '%d' )
    );
    return ( $result !== false ) ? $wpdb->insert_id : false;
  }


  /**
   * Get mq_mail records
   */
  private function get_mq_mail_data( $data = array() ) {
    global $wpdb;
    $table_name = $wpdb->prefix . "mq_mail";
    if( empty( $data ) || empty( $data['post_id'] ) ){
      return false;
    }
    $result = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, error_ids, status, send_date, estimated_date FROM {$table_name} WHERE post_id = %d",
        $data['post_id']
      )
    );
    return $result;
  }

  /**
   * Get mq_queue records
   */
  private function get_mq_queue_data( $data = array() ) {
    global $wpdb;
    $table_name = $wpdb->prefix . "mq_queue";
    if( empty( $data ) || empty( $data['mail_id'] ) ){
      return false;
    }
    $result = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, mail_id, status, send_date, estimated_date, address, mail_to_name FROM {$table_name} WHERE mail_id = %d",
        $data['mail_id']
      )
    );
    return $result;
  }

  
  /**
   * Create DB Table
   */
  private function create_table_queue(){
    return $this->create_table( 'mq_queue', array(
      'id'  => 'bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT',
      'mail_id' => 'bigint(20) UNSIGNED NOT NULL',
      'status'  => 'int(11) DEFAULT 0 NOT NULL',
      'send_date' => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
      'estimated_date'  => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
      'address' => 'text NOT NULL',
      'mail_to_name' => 'text NOT NULL'
    ) );
  }

  private function create_table_mail(){
    return $this->create_table( 'mq_mail', array(
      'id'  => 'bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT',
      'post_id' => 'bigint(20) UNSIGNED NOT NULL',
      'error_ids' => 'text NOT NULL',
      'status'  => 'int(11) DEFAULT 0 NOT NULL',
      'send_date' => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
      'estimated_date'  => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL"
    ) );
  }

  private function create_table( $table_label, $query_list ){
    global $wpdb;
    $table_name = $wpdb->prefix . $table_label;
    if( $wpdb->get_var( "show tables like '$table_name'" ) == $table_name ) {
      return false;
    }
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (";
    foreach( $query_list as $label => $body ) {
      $sql .= $label . ' ' . $body . ",\n";
    }
    $sql .= "UNIQUE KEY id (id) ) $charset_collate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    return true;
  }

  /**
   * Delete DB table row
   */
  private function delete_row_queue( $arg ){
    $arg['table'] = 'mq_queue';
    return $this->delete_row( $arg['table'], $arg['where'], $arg['format'] );
  }
  
  private function delete_row_mail( $arg ){
    $arg['table'] = 'mq_mail';
    return $this->delete_row( $arg['table'], $arg['where'], $arg['format'] );
  }

  private function delete_row( $table, $where ){
    global $wpdb;
    return $wpdb->delete( $wpdb->prefix . $table, $where, $format );
  }

  /**
   * Delete DB table
   */
  private function delete_table_queue(){
    return $this->delete_table( 'mq_queue' );
  }

  private function delete_table_mail(){
    return $this->delete_table( 'mq_mail' );
  }

  private function delete_table( $table_label ){
    global $wpdb;
    $table_name = $wpdb->prefix . $table_label;
    if( $wpdb->get_var( "show tables like '$table_name'" ) != $table_name ) {
      return false;
    }
    $sql = "DROP TABLE ". $table_name;
    $wpdb->query( $sql );
    return true;
  }


  /**
   * Other Methods
   */
  public function revers_key_values( $hash ){
    /*
    $hash = array(
      'レストランA' => "A_01@example.com",
      'レストランB' => "BE_common@example.com, B_01@example.com",
      'レストランC' => "CD_common@example.com",
      'レストランD' => "CD_common@example.com",
      'レストランE' => "BE_common@example.com"
    );
    */

    $list = array();
    foreach( $hash as $name => $address_csv ){
      $address_list = explode( ',', $address_csv );
      foreach( $address_list as $key => $address ){
        $address_list[$key] = trim( $address );
        $list[ $address_list[$key] ] = array();
      }
      $hash[$name] = $address_list;
    }    
    foreach( $hash as $name => $address_arr ){
      foreach( $address_arr as $address ){
        $list[$address][] = $name;
      }
    }
    return $list;
  }

  public function address_hash_arrange( $hash ){
    $list = array();
    foreach( $hash as $address => $name_list ){
      $name = implode('、', $name_list);
      if( is_null( $list[$name] ) && ! is_array( $list[$name] ) ){
        $list[$name] = array();
      }
      $list[$name][] = $address;
    }

    foreach( $list as $name => $address_list ){
      $list[$name] = implode( ',', $address_list );
    }
    return $list;
  }
}

?>