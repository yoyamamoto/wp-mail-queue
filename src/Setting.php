<?php
namespace Src;

class Setting {
  
  // このクラスのインスタンス
  protected static $instance = null;

  /**
   * インスタンス生成
   */
  public static function get_instance() {
    if ( null == self::$instance ) {
      self::$instance = new self;
    } // end if
    return self::$instance;
  } // end get_instance
  
  // DB wp_options table labelname 'option_name' 
  private $option_name;

  private $setting_group_name;

  private $capability;

  private $page;

  private $placeholder;
  /**
   * construct
   */
  private function __construct() {
    // $page
    $this->page = 'wp-mail-queue';

    // DB wp_options table 'option_name' prefix
    $this->option_name = 'wp-mail-queue';

    // register_setting group name
    $this->option_group = 'wp-mail-queue-setting-group';

    // メニューの追加
    add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

    // 設定項目の登録
    add_action( 'admin_init', array( $this, 'add_admin_init' ) );

    // Allow people to change what capability is required to use this plugin
    $this->capability = apply_filters('wp-mail-queue_cap', 'manage_options');

    register_activation_hook( WMQ_FILE, array( $this, 'activate_options' ) );
    
    register_uninstall_hook( WMQ_FILE, array( $this, 'delete_options' ) );
  }

  public function install_options(){
  }

  public function activate_options(){
  }

  public function delete_options(){
    //delete_option( $this->option_name );
  }

  /**
   * 設定画面の登録
   */
  function add_admin_menu() {
    add_options_page(
      '一斉メール送信の設定',
      '一斉メール送信の設定画面',
      $this->capability,
      $this->page,
      array( $this, 'wp_mail_queue_option_interface' )
    );
    return;
  }

  /**
   * 設定画面のインタフェース
   * 
   * @access public
   * @since 1.0
   */
  function wp_mail_queue_option_interface() {
    if( ! function_exists('current_user_can') || ! current_user_can('manage_options') ){
      die( __('Cheatin&#8217; uh?') );
    }
  ?>
    <div class="wrap">
      <h2>一斉メール送信 設定画面</h2>
      <form method="post" action="options.php">
        <?php
          settings_fields( $this->option_group );
          do_settings_sections( $this->page );
        ?>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /**
   * 設定のinit
   */
  function add_admin_init(){
    $settings = $this->get_setting_data();
    foreach( $settings as $section ):
      $section_id = $this->option_name . '_' . $section['section_id'];
      add_settings_section(
        $section_id, // Section ID
        $section['title'], // Title
        function(){return;}, // Callback
        $this->page // Page
      );
      foreach( $section['setting'] as $id => $title ){
        $this->set_settings_field(array(
          'id' => $id,
          'title' => $title,
          'section_id'=> $section_id
        ));
      }
    endforeach;
  }

  /**
   * 設定項目
   */
  private function get_setting_data(){
    return array(
      array(
        'section_id' => 'mail_setting_section',
        'title' => 'メールキュー（Cron）の設定',
        'setting' => array(
          'numoftransmissions' => '一度に処理するメール送信回数',
          'admin' => '管理者アドレス'
        )
      ),
      array(
        'section_id' => 'mail_info_section',
        'title' => 'メール送信者情報',
        'setting' => array(
          'from' => '送信元アドレス',
          'repleyto' => '返信先アドレス',
          'cc' => 'Ccアドレス',
          'encoding' => 'エンコード（Encoding）',
          'charset' => '文字コード（CharSet）',
          'ishtml' => 'HTMLのメール形式を送る',
          'signature' => 'メール署名'
        )
      )
    );
  }
  
  private function set_settings_field( $data ){
    //$id, $title, $section_id
    extract( $data );
    $sanitize_callback = 'sanitize_' . $id;
    $setting_callback = 'setting_fields_' . $id;
    $id = $this->option_name . '_' . $id;
    $value = get_option( $id, '' );
    // オプションフィールドの登録
    register_setting(
      $this->option_group,
      $id,
      array( $this, $sanitize_callback )
    );
    // セッティングセクションへ追加するフィールドの定義
    add_settings_field(
      $id, // ID
      $title, // Title
      array( $this, $setting_callback ), // Callback
      $this->page, // Page
      $section_id, // Container section id
      array( 'id' => $id, 'value' => $value )
    );
  }


  /**
   * input validation callback
   */
  public function notice( $input ){
    add_settings_error(
      'active_twitter',
      'active-twitter-validation_error',
      $input,
      'update'
    );
  }
  public function sanitize_password( $input ){
    define('crypt_pass','VNCLiX9BaeuzfaR');//暗号鍵
    define('crypt_method','aes-128-cbc');//method
    define('crypt_iv','FpXe2VNEVwLhJa8a');//iv 16バイト
    $input=openssl_encrypt ($input,crypt_method, crypt_pass, true, crypt_iv);//暗号化
    $input=bin2hex($input);//16進数に
    //$this->notice( $input );
    return $input;
  }

  public function sanitize_numoftransmissions( $input ){ return $input; }
  public function sanitize_admin( $input ){ return $input; }
  public function sanitize_from( $input ){ return $input; }
  public function sanitize_repleyto( $input ){ return $input; }
  public function sanitize_cc( $input ){ return $input; }
  public function sanitize_encoding( $input ){ return $input; }
  public function sanitize_charset( $input ){ return $input; }
  public function sanitize_ishtml( $input ){ return $input; }
  public function sanitize_signature( $input ){ return $input; }

  /**
   * Callback methods
   */

  public function default_field( $id, $value ){
    ?>
    <input type="text" class="regular-text" id="<?php echo $id; ?>" name="<?php echo $id; ?>" value="<?php echo esc_attr( $value ); ?>" />
   <?php
  }
  public function setting_fields_numoftransmissions( $arg ){
    ?>
    <select id="<?php echo $arg['id']; ?>" name="<?php echo $arg['id']; ?>">
      <option value="">選択してください</option>
      <?php
        for( $i = 1; $i <= 10; $i++ ):
          echo '<option value="' . $i*10 . '"' . ( ( (int)$arg['value'] === $i*10 ) ? 'selected' : '') .'>' . $i*10 . '</option>';
        endfor;
      ?>
    </select>
    <?php
  }
  public function setting_fields_admin( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }  
  public function setting_fields_from( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }
  public function setting_fields_repleyto( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }
  public function setting_fields_cc( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }
  public function setting_fields_encoding( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }
  public function setting_fields_charset( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }
  public function setting_fields_ishtml( $arg ){
    $this->default_field( $arg['id'], $arg['value'] );
  }
  public function setting_fields_signature( $arg ){
    ?>
      <textarea class="large-text code" rows="5" id="<?php echo $arg['id']; ?>" name="<?php echo $arg['id']; ?>"><?php echo esc_attr( $arg['value'] ); ?></textarea>
    <?php
  }

} // end class
?>