<?php
class Database{
  private $db;

	public function __construct(){
    // DBと接続
    $this->db_connect_mysqli();
  }

  public function __destruct(){
    // DB接続を閉じる
    $this->db->close();
  }

  private function db_connect_mysqli(){
    $this->db = new mysqli(
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

  private function get_mq_mail_tables( $id = null, $status = null, $limit = true ){
    $bind_param = array();
    $bind_param[0] = '';
    $where = array();
    if( $id !== null ){
      $where[] = "id = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$id;
    }
    if( $status !== null ){
      $where[] = "status = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$status;
    }
    $sql = "SELECT id, queue_ids, status, subject, text FROM wp_mq_mail";
    $sql .= ( ! empty( $where ) ) ? ' WHERE ' . implode(' AND ', $where ) : '';
    $sql .= " ORDER by ID";
    $sql .= ( $limit === true )? " LIMIT 10" : '';

    if ( $stmt = $this->db->prepare( $sql ) ) {
      if( ! empty( $bind_param[0] ) ){
        // クエリにパラメータをバインド
        call_user_func_array(
          array( $stmt, 'bind_param' ),
          $bind_param
        );
      }
      // クエリを実行
      $stmt->execute();
      $result = $stmt->get_result();
      $return = $result->fetch_all(MYSQLI_ASSOC);
      $result->close();
    }
    return $return;
  }

  private function update_mq_mail_tables( $id, $label, $value ){
    $sql = "UPDATE wp_mq_mail SET " . $label . "=? WHERE id=?";
    if ( $stmt = $this->db->prepare( $sql ) ) {
      // クエリにパラメータをバインド
      if( gettype( $value ) === "integer" ) {
        $type = 'ii';
      }else{
        $type = 'si';
      }
      $stmt->bind_param( $type, $value, $id );
      // クエリを実行
      $stmt->execute();
      $stmt->close();
    }
    return true;
  }

  /**
   * get_mq_queue_tables()
   * コンテンツキューのidを指定すると送信キューを取得する
   */
  private function get_mq_queue_tables( $mail_id = null, $status = null, $limit = true ){
    $bind_param = array();
    $bind_param[0] = '';
    $where = array();
    if( $mail_id !== null ){
      $where[] = "mail_id = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$mail_id;
    }
    if( $status !== null ){
      $where[] = "status = ?";
      $bind_param[0] .= 'i';
      $bind_param[] = &$status;
    }
    $sql = "SELECT id, mail_id, status, mail_to FROM wp_mq_queue";
    $sql .= ( ! empty( $where ) ) ? ' WHERE ' . implode(' AND ', $where ) : '';
    $sql .= " ORDER by ID";
    $sql .= ( $limit === true )? " LIMIT 10" : '';

    if ( $stmt = $this->db->prepare( $sql ) ) {
      if( ! empty( $bind_param[0] ) ){
        // クエリにパラメータをバインド
        call_user_func_array(
          array( $stmt, 'bind_param' ),
          $bind_param
        );
      }
      // クエリを実行
      $stmt->execute();
      $result = $stmt->get_result();
      $return = $result->fetch_all(MYSQLI_ASSOC);
      $result->close();
    }
    return $return;
  }

  /**
   * 'id'  => 'bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT',
   * 'mail_id' => 'bigint(20) UNSIGNED NOT NULL',
   * 'status'  => 'int(11) DEFAULT 0 NOT NULL',
   * 'send_date' => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
   * 'estimated_date'  => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL",
   * 'mail_to' => 'text NOT NULL'
   */
  private function update_mq_queue_tables( $id, $label, $value ){
    $sql = "UPDATE wp_mq_queue SET " . $label . "=? WHERE id=?";
    if ( $stmt = $this->db->prepare( $sql ) ) {
      // クエリにパラメータをバインド
      if( gettype( $value ) === "integer" ) {
        $type = 'ii';
      }else{
        $type = 'si';
      }
      $stmt->bind_param( $type, $value, $id );
      // クエリを実行
      $stmt->execute();
      $stmt->close();
    }
    return true;
  }

}
//new partylabel_cron();

?>