-- 1. Criar a tabela de Setores (Referência de Prioridade)
CREATE TABLE Setor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_setor VARCHAR(50) NOT NULL,
    nivel_prioridade INT NOT NULL DEFAULT 1
);

-- 2. Criar a tabela de Solicitantes (Profissionais)
CREATE TABLE Solicitante (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    setor_id INT,
    total_gasto_acumulado DECIMAL(10,2) DEFAULT 0.00,
    CONSTRAINT fk_setor FOREIGN KEY (setor_id) REFERENCES Setor(id)
);

-- 3. Criar a tabela de Galeria (Modelos Prontos)
CREATE TABLE Galeria_Modelos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_modelo VARCHAR(100) NOT NULL,
    descricao TEXT,
    peso_estimado DECIMAL(10,2),
    link_stl VARCHAR(255),
    foto_exemplo VARCHAR(255)
);

-- 4. Criar a tabela de Estoque de Filamento
CREATE TABLE Estoque_Filamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cor VARCHAR(50) NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- PLA, PETG, etc
    peso_atual_gramas DECIMAL(10,2) NOT NULL,
    status_alerta BOOLEAN DEFAULT FALSE
);

-- 5. Criar a tabela de Pedidos (Onde tudo se une)
CREATE TABLE Pedido (
    id INT PRIMARY KEY AUTO_INCREMENT,
    solicitante_id INT NOT NULL,
    modelo_id INT NULL,
    filamento_id INT NOT NULL,
    finalidade VARCHAR(50),
    data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_limite DATE,
    prioridade_bandeira INT DEFAULT 1,
    peso_final_gasto DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'Pendente',
    CONSTRAINT fk_solicitante FOREIGN KEY (solicitante_id) REFERENCES Solicitante(id),
    CONSTRAINT fk_modelo FOREIGN KEY (modelo_id) REFERENCES Galeria_Modelos(id),
    CONSTRAINT fk_filamento FOREIGN KEY (filamento_id) REFERENCES Estoque_Filamento(id)
);

-- 6. Tabela de Histórico (Auditoria de Saída)
CREATE TABLE Historico_Estoque (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filamento_id INT NOT NULL,
    quantidade_movimentada DECIMAL(10,2),
    tipo_movimentacao VARCHAR(10), -- 'Entrada' ou 'Saída'
    data_movimentacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_filamento FOREIGN KEY (filamento_id) REFERENCES Estoque_Filamento(id)
);

ALTER TABLE Solicitante 
ADD COLUMN tipo_usuario VARCHAR(20) DEFAULT 'comum'; 
-- Pode ser 'admin' ou 'comum'
UPDATE Solicitante 
SET tipo_usuario = 'admin' 
WHERE email = 'yuri.apolinario@docente.pr.senac.br'; -- Yuri como admin

UPDATE Solicitante 
SET tipo_usuario = 'admin' 
WHERE email = 'luiz.francisco@docente.pr.senac.br'; -- Luiz como admin

-- 2° parte 
-- 1. Cadastrando os Setores com suas Prioridades
-- A Secretaria tem prioridade 10 (Bandeira Vermelha)
INSERT INTO Setor (nome_setor, nivel_prioridade) VALUES ('Secretaria', 10);
INSERT INTO Setor (nome_setor, nivel_prioridade) VALUES ('Instrutores', 1);

-- Bruna e Luiz no Setor 1 (Prioridade 1)
INSERT INTO Solicitante (nome, email, setor_id) 
VALUES ('Bruna Stefani', 'Bruna.custodio@docente.pr.senac.br', 2);

INSERT INTO Solicitante (nome, email, setor_id) 
VALUES ('Luiz Gustavo', 'luiz.francisco@docente.pr.senac.br', 1);

-- Juliana no Setor 10 (Prioridade 10)
INSERT INTO Solicitante (nome, email, setor_id) 
VALUES ('Juliana Santos', 'juliana.santos@pr.senac.br', 1);

INSERT INTO Solicitante (nome, email, setor_id) 
VALUES ('Yuri Apolinario', 'yuri.apolinario@docente.pr.senac.br', 2);


-- 16/03/2026


-- 3. Abastecendo o Estoque inicial (Rolos de 1kg)
INSERT INTO Estoque_Filamento (cor, tipo, peso_atual_gramas, status_alerta) VALUES ('Branco', 'PLA', 1000.00, FALSE);
INSERT INTO Estoque_Filamento (cor, tipo, peso_atual_gramas, status_alerta) VALUES ('Preto', 'PLA', 850.00, FALSE);
INSERT INTO Estoque_Filamento (cor, tipo, peso_atual_gramas, status_alerta) VALUES ('Azul', 'PETG', 1200.00, FALSE);

-- 4. Alimentando a Galeria de Modelos (Itens padrão)
INSERT INTO Galeria_Modelos (nome_modelo, descricao, peso_estimado) 
VALUES ('Chaveiro Logo Escola', 'Chaveiro retangular com logo em relevo', 12.50);
INSERT INTO Galeria_Modelos (nome_modelo, descricao, peso_estimado) 
VALUES ('Suporte Celular Universal', 'Suporte articulado para mesa', 45.00);

CREATE OR REPLACE VIEW Fila_Prioritaria AS
SELECT 
    P.id AS 'Cod_Pedido',
    S.nome AS 'Solicitante',
    ST.nome_setor AS 'Setor',
    ST.nivel_prioridade AS 'Prioridade',
    M.nome_modelo AS 'Modelo',
    F.cor AS 'Cor_Filamento',
    P.status AS 'Status',
    P.data_solicitacao AS 'Data'
FROM Pedido P
JOIN Solicitante S ON P.solicitante_id = S.id
JOIN Setor ST ON S.setor_id = ST.id
JOIN Galeria_Modelos M ON P.modelo_id = M.id
JOIN Estoque_Filamento F ON P.filamento_id = F.id
ORDER BY ST.nivel_prioridade DESC, P.data_solicitacao ASC;

SELECT * FROM Fila_Prioritaria;

CREATE OR REPLACE VIEW Alerta_Estoque_Baixo AS
SELECT 
    id AS 'ID_Filamento',
    cor AS 'Cor',
    tipo AS 'Material',
    peso_atual_gramas AS 'Peso_Restante',
    CASE 
        WHEN peso_atual_gramas <= 100 THEN 'CRÍTICO'
        WHEN peso_atual_gramas <= 200 THEN 'BAIXO'
        ELSE 'OK'
    END AS 'Nivel_Alerta'
FROM Estoque_Filamento
WHERE peso_atual_gramas <= 200;

DELIMITER //

CREATE TRIGGER tgr_baixa_estoque_concluido
AFTER UPDATE ON Pedido
FOR EACH ROW
BEGIN
    -- Verifica se o status foi alterado para 'Concluído' agora
    IF NEW.status = 'Concluído' AND OLD.status <> 'Concluído' THEN
        UPDATE Estoque_Filamento 
        SET peso_atual_gramas = peso_atual_gramas - (
            SELECT peso_estimado 
            FROM Galeria_Modelos 
            WHERE id = NEW.modelo_id
        )
        WHERE id = NEW.filamento_id;
    END IF;
END; //

DELIMITER ;

INSERT INTO Pedido (solicitante_id, modelo_id, filamento_id, finalidade, status)
VALUES (
    (SELECT id FROM Solicitante WHERE nome = 'Juliana Santos'), 
    (SELECT id FROM Galeria_Modelos WHERE nome_modelo = 'Chaveiro Logo Escola'), 
    (SELECT id FROM Estoque_Filamento WHERE cor = 'Azul'), 
    'Brinde para evento institucional',
    'Pendente'
); 

INSERT INTO Galeria_Modelos (nome_modelo, descricao, peso_estimado, link_stl, foto_exemplo) 
VALUES (
    'Chaveiro Logo Escola', 
    'Chaveiro retangular com logo em relevo', 
    12.50, 
    'modelos/chaveiro_senac.stl', 
    'fotos/chaveiro_logo.jpg'
);
ALTER TABLE Estoque_Filamento 
ADD CONSTRAINT chk_peso_positivo CHECK (peso_atual_gramas >= 0);
-- Ajustes para a interface OctoView
ALTER TABLE Estoque_Filamento
ADD COLUMN cor_hex VARCHAR(7) DEFAULT '#888888';

ALTER TABLE Pedido
ADD COLUMN observacoes VARCHAR(255) NULL,
ADD COLUMN imagem_referencia VARCHAR(255) NULL;

UPDATE Estoque_Filamento SET cor_hex = '#e8e8e8' WHERE cor = 'Branco' AND tipo = 'PLA';
UPDATE Estoque_Filamento SET cor_hex = '#222222' WHERE cor = 'Preto' AND tipo = 'PLA';
UPDATE Estoque_Filamento SET cor_hex = '#3b82f6' WHERE cor = 'Azul' AND tipo = 'PETG';

UPDATE Solicitante SET tipo_usuario = 'admin' WHERE email = 'yuri.apolinario@docente.pr.senac.br';
UPDATE Solicitante SET tipo_usuario = 'admin' WHERE email = 'luiz.francisco@docente.pr.senac.br';

-- Padronizacao de status usados pela interface web
UPDATE Pedido SET status = 'Em Producao' WHERE status IN ('Em Produção', 'Em Producao');
UPDATE Pedido SET status = 'Concluido' WHERE status IN ('Concluído', 'Concluido');
UPDATE Pedido SET status = 'Cancelado' WHERE status = 'Cancelado';

ALTER TABLE Pedido
MODIFY COLUMN finalidade VARCHAR(255);

-- Ajustes adicionais para dashboard, foto de perfil e baixa real de estoque
ALTER TABLE Solicitante
ADD COLUMN foto_perfil VARCHAR(255) NULL;

-- Exemplos de insercao/atualizacao das fotos de perfil
UPDATE Solicitante
SET foto_perfil = 'uploads/perfis/yuri.png'
WHERE email = 'yuri.apolinario@docente.pr.senac.br';

UPDATE Solicitante
SET foto_perfil = 'uploads/perfis/luiz.png'
WHERE email = 'luiz.francisco@docente.pr.senac.br';

UPDATE Solicitante
SET foto_perfil = 'uploads/perfis/bruna.png'
WHERE email = 'bruna.custodio@docente.pr.senac.br';

UPDATE Solicitante
SET foto_perfil = 'uploads/perfis/juliana.png'
WHERE email = 'juliana.santos@pr.senac.br';

-- Opcional: normaliza pedidos antigos sem peso final usando o peso estimado do modelo
UPDATE Pedido p
INNER JOIN Galeria_Modelos gm ON gm.id = p.modelo_id
SET p.peso_final_gasto = gm.peso_estimado
WHERE p.peso_final_gasto IS NULL
  AND gm.peso_estimado IS NOT NULL;

-- 19/03/2026 - Autenticacao JWT, usuarios e senha padrao
ALTER TABLE Solicitante
ADD COLUMN usuario VARCHAR(60) NULL AFTER email,
ADD COLUMN matricula VARCHAR(20) NULL AFTER usuario,
ADD COLUMN senha_hash VARCHAR(255) NULL AFTER matricula,
ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER senha_hash;

ALTER TABLE Solicitante
ADD CONSTRAINT uq_solicitante_usuario UNIQUE (usuario),
ADD CONSTRAINT uq_solicitante_matricula UNIQUE (matricula);

-- Ajuste os valores conforme a matricula real de cada usuario
-- Senha padrao = matricula
UPDATE Solicitante
SET usuario = 'yuri.apolinario',
    matricula = '1001',
    senha_hash = '$2y$10$gfsu7kmj7TFyTSjK9j6K5O3YgFya3tKwotW8d1/xeiA8//rqrux/2',
    ativo = 1
WHERE email = 'yuri.apolinario@docente.pr.senac.br';

UPDATE Solicitante
SET usuario = 'luiz.gustavo',
    matricula = '1002',
    senha_hash = '$2y$10$RztkcokcYRQa8eMvFR7c3.I4cApPBQcyfBwHEyaVrAU7lovTzniEi',
    ativo = 1
WHERE email = 'luiz.francisco@docente.pr.senac.br';

UPDATE Solicitante
SET usuario = 'bruna.stefani',
    matricula = '1003',
    senha_hash = '$2y$10$q3g05xU/MCtClfjEWTasaO33RPRM/xAexgE2.jjgRphdfEN/DnQc2',
    ativo = 1
WHERE email = 'bruna.custodio@docente.pr.senac.br';

UPDATE Solicitante
SET usuario = 'juliana.santos',
    matricula = '1004',
    senha_hash = '$2y$10$jj3g9rbgd2FhB75SASuc9u/Yx8rAEMhUzdtTCWuloEQD2lGrgSNBO',
    ativo = 1
WHERE email = 'juliana.santos@pr.senac.br';

-- Antes de aplicar NOT NULL, confirme se nao existe nenhum registro pendente
SELECT id, nome, email, usuario, matricula, senha_hash, ativo
FROM Solicitante
WHERE usuario IS NULL
   OR matricula IS NULL
   OR senha_hash IS NULL
   OR ativo IS NULL;

-- Se a consulta acima nao retornar linhas, execute o bloco abaixo
ALTER TABLE Solicitante
    MODIFY COLUMN usuario VARCHAR(60) NOT NULL,
    MODIFY COLUMN matricula VARCHAR(20) NOT NULL,
    MODIFY COLUMN senha_hash VARCHAR(255) NOT NULL,
    MODIFY COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1;

-- Exemplo de insercao de novo administrador
-- Senha padrao: 2001
INSERT INTO Solicitante (nome, email, usuario, matricula, setor_id, total_gasto_acumulado, tipo_usuario, foto_perfil, senha_hash, ativo)
VALUES ('Marcos Oliveira', 'marcos.oliveira@pr.senac.br', 'marcos.oliveira', '2001', 1, 0.00, 'admin', NULL, '$2y$10$4nj0GRCPx2YWYae7MWAN0OX1UzUZe2mPnSNuLFlmB3eLizo0PcRjC', 1);

-- Exemplo de insercao de novo solicitante
-- Senha padrao: 2002
INSERT INTO Solicitante (nome, email, usuario, matricula, setor_id, total_gasto_acumulado, tipo_usuario, foto_perfil, senha_hash, ativo)
VALUES ('Ana Silva', 'ana.silva@pr.senac.br', 'ana.silva', '2002', 2, 0.00, 'comum', NULL, '$2y$10$UdkMJjCGlcrsai2eNhVAFea3lt1tYaFXyPhv.6cqSRbSMwP3ORtsm', 1);
