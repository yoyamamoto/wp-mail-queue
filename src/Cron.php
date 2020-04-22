<?php
require_once __DIR__.'/../../../../wp-load.php';

class wp_mail_queue_cron {
  private $db;

	public function __construct(){
    $this->db_connect_mysqli();
    $this->send_queue();
    $this->check_mailqueue();
  }

  public function __destruct(){
    $this->db->close();
  }

  /**
   * コンテンツキューを更新
   */
  private function check_mailqueue(){
    // statusが0のコンテンツキューを取得
    $mq_mails = $this->get_mq_mail_tables( array(
      'status' => 0
    ) );
    foreach( $mq_mails as $mq_mail ){
      // 送信キューの中から対象IDのコンテンツデータを全て抜き出す
      $mq_queues = $this->get_mq_queue_tables(array(
        'mail_id' => $mq_mail['id']
      ));
      // 送信キューのステータスを抜き出す
      $mq_queues_status = array_column( $mq_queues, 'status' );
      if( in_array( 0, $mq_queues_status, true ) === false && in_array( -1, $mq_queues_status, true ) === false ){
        // 正常
        $status = 1;
      }else if( in_array( 0, $mq_queues_status, true ) === false && in_array( -1, $mq_queues_status, true ) === true){
        // 送信エラー有り
        $status = -1;
      }else{
        // 未送信有り
        $status = 0;
      }
      // ステータスと送信完了時刻の更新
      if( $status !== 0 ){
        $return = $this->update_mq_mail_tables( array(
          'id' => $mq_mail['id'],
          'database' => array(
            'status' => $status,
            'send_date' => current_time( 'mysql' )
          )
        ) );
      }
    }
  }

  /**
   * 送信キューとコンテンツキューを取得してメール送信
   */
  private function send_queue(){
    $limit = filter_var(
      get_option( 'wp-mail-queue_numoftransmissions' ),
      FILTER_VALIDATE_INT,
      array(
        'options' => array(
          'default' => 0
        )
      )
    );
    // 未送信のキューを取得
    $queues = $this->get_mq_queue_tables(array(
     'status' => 0,
     'limit' => $limit
    ));

    // 処理
    foreach( $queues as $mq_queue ){
      $mq_mails = $this->get_mq_mail_tables( array(
        'id' => (int)$mq_queue['mail_id']
      ) );
      $post_id = $mq_mails[0]['post_id'];
      // メール送信
      if( $this->sendmail( array(
        'address' => $mq_queue['address'],
        'mail_to_name' => $mq_queue['mail_to_name'],
        'subject' => get_post_field( 'post_title', $post_id ),
        'body' => get_post_field( 'post_content', $post_id )
      ) ) === true ){
        $return = $this->update_mq_queue_tables( array(
          'id' => $mq_queue['id'],
          'database' => array(
            'status' => 1,
            'send_date' => current_time( 'mysql' )
          )
        ) );
        echo $mq_queue['mail_to_name'] . 'へ送信完了';
      }else{
        $return = $this->update_mq_queue_tables( array(
          'id' => $mq_queue['id'],
          'database' => array(
            'status' => -1,
            'send_date' => current_time( 'mysql' )
          )
        ) );
      }
    }
  }

  /**
   * mb_send_mail
   */
  private function sendmail( $arg ){
    date_default_timezone_set('Asia/Tokyo');
    //言語と文字コードの使用宣言
    mb_language("ja");
    mb_internal_encoding("UTF-8");
    error_reporting(E_ALL);
    
    preg_match(
      '/^(.*)<(.*)>$/',
      get_option( 'wp-mail-queue_from' ),
      $match
    );
    if( filter_var( $match[2] , FILTER_VALIDATE_EMAIL) === false ){
      return false;
    }
    $from = mb_encode_mimeheader( $match[1] ) . '<' . $match[2] . '>';
    $header = "MIME-Version: 1.0\r\n"
    . "Content-Transfer-Encoding: " . get_option( 'wp-mail-queue_encoding' )  . "\r\n"
    . "Content-Type: text/plain; charset=" . get_option( 'wp-mail-queue_charset' )  . "\r\n"
    . "From: " . $from . "\r\n"
    . "Reply-To: " . $from;
    $mail_to = $arg['address'];
    $subject = $arg['subject'];
    $body = $arg['mail_to_name'] . " 御中\n\r" . $arg['body'];


    $admin = get_option( 'wp-mail-queue_admin' );
    if( filter_var( $admin, FILTER_VALIDATE_EMAIL ) === false ){
      preg_match(
        '/^(.*)<(.*)>$/',
        $admin,
        $admin_match
      );
      if( filter_var( $admin_match[2] , FILTER_VALIDATE_EMAIL) === false ){
        return false;
      }
      $admin = mb_encode_mimeheader( $admin_match[1] ) . '<' . $admin_match[2] . '>';
    }

    $error_to = filter_var(
      $admin,
      FILTER_VALIDATE_EMAIL,
      array(
        'options' => array(
          'default' => $from
        )
      )
    );

    //d( esc_html( $from ) );
    //d( $header );
    //d( $mail_to );
    //d( $subject );
    //d( $body );
    
    if( $_SERVER["HTTP_HOST"] !== "localhost" ){
      return mb_send_mail( $mail_to, $subject, $body, $header, "-f " . $error_to);
    }else{
      var_dump('test code');
      return true;
    }
  }

  private function db_connect_mysqli(){
    $this->db = new \mysqli(
      DB_HOST,
      DB_USER,
      DB_PASSWORD,
      DB_NAME
    );
    // 接続確認
    if ($this->db->connect_error) {
      echo $this->db->connect_error;
      exit();
    }
    // 文字セット
    $this->db->set_charset("utf8");
  }

  /**
   * @param int $arg['id']
   * @param int $arg['status'] (0 or 1 or 2 or -1)
   * @param int $arg['limit']
   * @return array
   */
  private function get_mq_mail_tables( $arg = array() ){
    $bind_param = array();
    $bind_param[0] = '';
    $where = array();
    if( ! empty( $arg['id'] ) ){
      $where[] = "id = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$arg['id'];
    }
    if( ! empty ( $arg['status'] ) ){
      $where[] = "status = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$arg['status'];
    }
    if( ! empty ( $arg['limit'] ) ){
      $bind_param[0] .= 'i';
      $bind_param[] = &$arg['limit'];
    }

    $sql = "SELECT * FROM wp_mq_mail";
    $sql .= ( ! empty( $where ) ) ? ' WHERE ' . implode(' AND ', $where ) : '';
    $sql .= " ORDER by ID";
    $sql .= ( ! empty( $arg['limit'] ) )? " LIMIT ?" : '';

    if ( $stmt = $this->db->prepare( $sql ) ){
      if( ! empty( $bind_param[0] ) ){
        // クエリにパラメータをバインド
        call_user_func_array(
          array( $stmt, 'bind_param' ),
          $bind_param
        );
      }
    
      // クエリを実行
      $stmt->execute();

      // 結果をバッファに保存し、パフォーマンスとメモリのコストを下げる。
      $stmt->store_result();

      // 結果を取得
      $result = $this->fetch_all( $stmt );
        
      $stmt->close();

      return $result;
    }
    return array();
  }

  /**
   * get_mq_queue_tables()
   * コンテンツキューのidを指定すると送信キューを取得する
   */
  private function get_mq_queue_tables( $arg = array() ){
    $bind_param = array();
    $bind_param[0] = '';
    $where = array();
    if( ! empty( $arg['mail_id'] ) ){
      $where[] = "mail_id = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$arg['mail_id'];
    }
    if( isset( $arg['status'] ) ){
      $where[] = "status = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$arg['status'];
    }
    if( ! empty( $arg['limit'] ) ){
      $bind_param[0] .= 'i';
      $bind_param[] = &$arg['limit'];
    }
    $sql = "SELECT * FROM wp_mq_queue";
    $sql .= ( ! empty( $where ) ) ? ' WHERE ' . implode(' AND ', $where ) : '';
    $sql .= " ORDER by ID";
    $sql .= ( ! empty ( $arg['limit'] ) ) ? " LIMIT ?" : '';

    if ( $stmt = $this->db->prepare( $sql ) ){
      if( ! empty( $bind_param[0] ) ){
        // クエリにパラメータをバインド
        call_user_func_array(
          array( $stmt, 'bind_param' ),
          $bind_param
        );
      }

      // クエリを実行
      $stmt->execute();
      
      // 結果をバッファに保存し、パフォーマンスとメモリのコストを下げる。
      $stmt->store_result();

      // 結果を取得
      $result = $this->fetch_all( $stmt );
      
      $stmt->close();

      return $result;
    }
    return array();
  }

  /**
   * @param object $stmt
   * @return array
   * via http://www.akiyan.com/blog/archives/2011/07/php-mysqli-fetchall.html
   */
  private function fetch_all(& $stmt) {
    $hits = array();
    $params = array();
    $meta = $stmt->result_metadata();
    while ($field = $meta->fetch_field()) {
      $params[] = &$row[$field->name];
    }
    call_user_func_array(array($stmt, 'bind_result'), $params);
    while ($stmt->fetch()) {
      $c = array();
      foreach($row as $key => $val) {
        $c[$key] = $val;
      }
      $hits[] = $c;
    }
    return $hits;
  }

  /**
   * wrapper methods
   * @param array $arg table_label => value
   * @return bool
   */
  private function update_mq_mail_tables( $arg = array() ){
    return $this->update_tables( 'wp_mq_mail', $arg );
  }

  /**
   * wrapper methods
   * @param array $arg table_label => value
   * @return bool
   */
  private function update_mq_queue_tables( $arg = array() ){
    return $this->update_tables( 'wp_mq_queue', $arg );
  }

  /**
   * Wordpressno独自テーブルをアップデートする
   * @param string $table_name
   * @param array $arg table_label => value
   * @return bool
   */
  private function update_tables( $table_name,  $arg = array() ){
    if(
         empty( $arg['id'] )
      || empty( $arg['database'] )
    ){
      return false;
    }
    $condition = array();
    $param = array('');
    foreach( $arg['database'] as $label => &$value ){
      $condition[] = $label . "=?";
      $param[] = &$value;
      $param[0] .= ( gettype( $value ) === 'integer' ) ? 'i':'s';
    }
    $param[0] .= 'i';
    $param[] = &$arg['id'];
    $sql = "UPDATE " . $table_name . " SET " . implode(', ', $condition) . " WHERE id=?";
    if ( $stmt = $this->db->prepare( $sql ) ) {
      call_user_func_array( array( $stmt, 'bind_param' ), $param);
      $return = $stmt->execute();
      $stmt->close();
      return $return;
    }
    return false;
  }

}
new wp_mail_queue_cron();

?>