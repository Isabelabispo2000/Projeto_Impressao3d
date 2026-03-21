-- Migracao Octo View para multi-unidades Senac
-- Estrutura alinhada com as tabelas reais do projeto:
-- Solicitante, Pedido, Impressora, Estoque_Filamento e Fila_Prioritaria

START TRANSACTION;

CREATE TABLE IF NOT EXISTS Unidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_unidade VARCHAR(120) NOT NULL,
    cidade VARCHAR(120) NOT NULL,
    estado CHAR(2) NOT NULL,
    codigo_senac VARCHAR(30) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_unidade_codigo_senac UNIQUE (codigo_senac)
);

INSERT INTO Unidade (nome_unidade, cidade, estado, codigo_senac)
SELECT 'Unidade Padrao', 'Nao Informada', 'PR', 'SENAC-DEFAULT'
WHERE NOT EXISTS (
    SELECT 1
    FROM Unidade
    WHERE codigo_senac = 'SENAC-DEFAULT'
);

ALTER TABLE Solicitante
    ADD COLUMN IF NOT EXISTS unit_id INT NULL AFTER ativo,
    ADD COLUMN IF NOT EXISTS escopo_acesso VARCHAR(20) NOT NULL DEFAULT 'colaborador' AFTER tipo_usuario;

UPDATE Solicitante
SET unit_id = (
    SELECT id
    FROM Unidade
    WHERE codigo_senac = 'SENAC-DEFAULT'
    LIMIT 1
)
WHERE unit_id IS NULL;

ALTER TABLE Solicitante
    MODIFY COLUMN unit_id INT NOT NULL;

ALTER TABLE Solicitante
    ADD CONSTRAINT fk_solicitante_unidade
        FOREIGN KEY (unit_id) REFERENCES Unidade(id);

CREATE INDEX idx_solicitante_unit_id ON Solicitante (unit_id);

ALTER TABLE Pedido
    ADD COLUMN IF NOT EXISTS unit_id INT NULL AFTER status;

UPDATE Pedido p
INNER JOIN Solicitante s ON s.id = p.solicitante_id
SET p.unit_id = s.unit_id
WHERE p.unit_id IS NULL;

ALTER TABLE Pedido
    MODIFY COLUMN unit_id INT NOT NULL;

ALTER TABLE Pedido
    ADD CONSTRAINT fk_pedido_unidade
        FOREIGN KEY (unit_id) REFERENCES Unidade(id);

CREATE INDEX idx_pedido_unit_id ON Pedido (unit_id);

ALTER TABLE Estoque_Filamento
    ADD COLUMN IF NOT EXISTS unit_id INT NULL AFTER cor_hex;

UPDATE Estoque_Filamento
SET unit_id = (
    SELECT id
    FROM Unidade
    WHERE codigo_senac = 'SENAC-DEFAULT'
    LIMIT 1
)
WHERE unit_id IS NULL;

ALTER TABLE Estoque_Filamento
    MODIFY COLUMN unit_id INT NOT NULL;

ALTER TABLE Estoque_Filamento
    ADD CONSTRAINT fk_estoque_filamento_unidade
        FOREIGN KEY (unit_id) REFERENCES Unidade(id);

CREATE INDEX idx_estoque_filamento_unit_id ON Estoque_Filamento (unit_id);

ALTER TABLE Historico_Estoque
    ADD COLUMN IF NOT EXISTS unit_id INT NULL AFTER data_movimentacao;

UPDATE Historico_Estoque he
INNER JOIN Estoque_Filamento ef ON ef.id = he.filamento_id
SET he.unit_id = ef.unit_id
WHERE he.unit_id IS NULL;

ALTER TABLE Historico_Estoque
    MODIFY COLUMN unit_id INT NOT NULL;

ALTER TABLE Historico_Estoque
    ADD CONSTRAINT fk_historico_estoque_unidade
        FOREIGN KEY (unit_id) REFERENCES Unidade(id);

CREATE INDEX idx_historico_estoque_unit_id ON Historico_Estoque (unit_id);

CREATE TABLE IF NOT EXISTS Impressora (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_impressora VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NULL,
    status_impressora VARCHAR(30) NOT NULL DEFAULT 'Disponivel',
    unit_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_impressora_unidade FOREIGN KEY (unit_id) REFERENCES Unidade(id)
);

CREATE INDEX idx_impressora_unit_id ON Impressora (unit_id);

DROP VIEW IF EXISTS Fila_Prioritaria;

CREATE VIEW Fila_Prioritaria AS
SELECT
    P.id AS Cod_Pedido,
    P.unit_id,
    U.nome_unidade,
    S.nome AS Solicitante,
    ST.nome_setor AS Setor,
    ST.nivel_prioridade AS Prioridade,
    M.nome_modelo AS Modelo,
    F.cor AS Cor_Filamento,
    P.status AS Status,
    P.data_solicitacao AS Data
FROM Pedido P
JOIN Solicitante S ON P.solicitante_id = S.id
JOIN Unidade U ON U.id = P.unit_id
JOIN Setor ST ON S.setor_id = ST.id
LEFT JOIN Galeria_Modelos M ON P.modelo_id = M.id
JOIN Estoque_Filamento F ON P.filamento_id = F.id
ORDER BY ST.nivel_prioridade DESC, P.data_solicitacao ASC;

UPDATE Solicitante
SET escopo_acesso = CASE
    WHEN COALESCE(tipo_usuario, 'comum') = 'admin' THEN 'admin_local'
    ELSE 'colaborador'
END
WHERE escopo_acesso NOT IN ('admin_local', 'admin_nacional', 'colaborador');

COMMIT;
