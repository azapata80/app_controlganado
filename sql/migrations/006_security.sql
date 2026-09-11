-- Sprint 6: autenticación, roles, bloqueo de acceso y auditoría.
-- No crea contraseñas predeterminadas. Al finalizar visite setup.php para crear
-- el primer administrador; setup.php se deshabilita automáticamente después.

CREATE TABLE users(
  id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(80) NOT NULL UNIQUE,display_name VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,role ENUM('ADMIN','OPERATOR','FINANCE','VIEWER') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,failed_login_count INT NOT NULL DEFAULT 0,locked_until DATETIME NULL,
  last_login_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE TABLE user_activity_log(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,username VARCHAR(80) NULL,action VARCHAR(80) NOT NULL,
  route VARCHAR(160) NOT NULL,request_method VARCHAR(10) NOT NULL,ip_address VARCHAR(45) NULL,details TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id,created_at),FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE INDEX idx_animals_status_group ON animals(status,group_id);
