USE SaludUSM;

-- 1. Prevision_de_salud
CREATE TABLE Prevision_de_salud (
    id_prevision INT AUTO_INCREMENT PRIMARY KEY,
    Nombre_prevision VARCHAR(50) NOT NULL UNIQUE
);

-- 2. Centros_medicos
CREATE TABLE Centros_medicos (
    id_cm INT AUTO_INCREMENT PRIMARY KEY,
    nombre_cm VARCHAR(100) NOT NULL,
    comuna VARCHAR(50) NOT NULL,
    region VARCHAR(50) NOT NULL
);

-- 3. Medicos
CREATE TABLE Medicos (
    rut_med VARCHAR(12) PRIMARY KEY,
    Nombre_med VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL UNIQUE
);

-- 4. Especialidades
CREATE TABLE Especialidades (
    id_espec INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL UNIQUE
);

-- 5. Estado
CREATE TABLE Estado (
    id_estado INT AUTO_INCREMENT PRIMARY KEY,
    tipo_estado VARCHAR(100) NOT NULL UNIQUE
);

-- 6. Diagnosticos
CREATE TABLE Diagnosticos (
    id_diag VARCHAR(10) PRIMARY KEY,
    descripcion VARCHAR(225) NOT NULL
);

-- 7. Medicos_centro
CREATE TABLE Medicos_centro (
    rut_med VARCHAR(12) NOT NULL,
    id_cm INT NOT NULL,
    PRIMARY KEY (rut_med, id_cm),
    CONSTRAINT FK_MedicosCentro_Medicos FOREIGN KEY (rut_med) 
        REFERENCES Medicos(rut_med) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_MedicosCentro_CentrosMedicos FOREIGN KEY (id_cm) 
        REFERENCES Centros_medicos(id_cm) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 8. Medico_especialidad
CREATE TABLE Medico_especialidad (
    rut_med VARCHAR(12) NOT NULL,
    id_espec INT NOT NULL,
    PRIMARY KEY (rut_med, id_espec),
    CONSTRAINT FK_MedicoEspecialidad_Medicos FOREIGN KEY (rut_med) 
        REFERENCES Medicos(rut_med) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_MedicoEspecialidad_Especialidades FOREIGN KEY (id_espec) 
        REFERENCES Especialidades(id_espec) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 9. Pacientes
CREATE TABLE Pacientes (
    rut_pac VARCHAR(12) PRIMARY KEY,
    id_prevision INT NOT NULL,
    Nombre_pac VARCHAR(100) NOT NULL,
    fecha_de_nacimiento DATE NOT NULL,
    sexo VARCHAR(15) NOT NULL,
    telefono_de_contacto VARCHAR(15) NOT NULL,
    comuna_de_residencia VARCHAR(50) NOT NULL,
    CONSTRAINT FK_Pacientes_Prevision FOREIGN KEY (id_prevision) 
        REFERENCES Prevision_de_salud(id_prevision) ON UPDATE CASCADE
);

-- 10. Citas
CREATE TABLE Citas (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    rut_pac VARCHAR(12) NOT NULL,
    rut_med VARCHAR(12) NOT NULL,
    id_cm INT NOT NULL,
    id_espec INT NOT NULL,
    id_estado INT NOT NULL,
    fecha_y_hora DATETIME NOT NULL,
    comentario VARCHAR(255) NULL,
    CONSTRAINT FK_Citas_Pacientes FOREIGN KEY (rut_pac) 
        REFERENCES Pacientes(rut_pac) ON UPDATE CASCADE,
    CONSTRAINT FK_Citas_Medicos FOREIGN KEY (rut_med) 
        REFERENCES Medicos(rut_med) ON UPDATE CASCADE,
    CONSTRAINT FK_Citas_CentrosMedicos FOREIGN KEY (id_cm) 
        REFERENCES Centros_medicos(id_cm) ON UPDATE CASCADE,
    CONSTRAINT FK_Citas_Especialidades FOREIGN KEY (id_espec) 
        REFERENCES Especialidades(id_espec) ON UPDATE CASCADE,
    CONSTRAINT FK_Citas_Estado FOREIGN KEY (id_estado) 
        REFERENCES Estado(id_estado) ON UPDATE CASCADE
);

-- 11. Atenciones
CREATE TABLE Atenciones (
    id_atencion INT AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    observaciones VARCHAR(500) NOT NULL,
    CONSTRAINT FK_Atenciones_Citas FOREIGN KEY (id_cita) 
        REFERENCES Citas(id_cita) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 12. Diagnosticos_atenciones
CREATE TABLE Diagnosticos_atenciones (
    id_atencion INT NOT NULL,
    id_diag VARCHAR(10) NOT NULL,
    PRIMARY KEY (id_atencion, id_diag),
    CONSTRAINT FK_DiagAtenciones_Atenciones FOREIGN KEY (id_atencion) 
        REFERENCES Atenciones(id_atencion) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT FK_DiagAtenciones_Diagnosticos FOREIGN KEY (id_diag) 
        REFERENCES Diagnosticos(id_diag) ON DELETE CASCADE ON UPDATE CASCADE
);

-- 13. Recetas
CREATE TABLE Recetas (
    id_recetas INT AUTO_INCREMENT PRIMARY KEY,
    id_atencion INT NOT NULL,
    medicamento VARCHAR(100) NOT NULL,
    dosis VARCHAR(100) NOT NULL,
    dias_tratamiento INT NOT NULL,
    CONSTRAINT FK_Recetas_Atenciones FOREIGN KEY (id_atencion) 
        REFERENCES Atenciones(id_atencion) ON DELETE CASCADE ON UPDATE CASCADE
);

