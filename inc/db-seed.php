<?php
/**
 * Données de référence (tables ueb_* de type "lookup") du thème.
 *
 * Contrairement à db-schema.php (qui ne crée QUE la structure des tables),
 * ce fichier insère les données de référence : régions, départements,
 * communes, facultés, diplômes, spécialités, filières, situations
 * matrimoniales, statuts socio-professionnels, nationalités, niveaux LMD,
 * mentions, statuts étudiants, langues, sports, arts.
 *
 * INSERT IGNORE est utilisé partout : comme chaque table a une contrainte
 * UNIQUE sur son code/nom, ré-exécuter ce script (à chaque activation du
 * thème, ou sur une install déjà peuplée) ne crée jamais de doublons et ne
 * lève jamais d'erreur.
 *
 * NE CONTIENT PAS les données candidats (ueb_preinscriptions, etc.) —
 * uniquement les tables de référence, qui sont les mêmes pour tout le monde.
 *
 * @package Preinscriptions_UEB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Version des données de référence. Réutilise UEB_DB_SCHEMA_VERSION
 * (défini dans db-schema.php) : les données et la structure évoluent
 * ensemble pour cette version 1.0. Si un jour on ajoute une nationalité
 * ou un statut socio-professionnel sans toucher à la structure des
 * tables, on pourra dissocier avec sa propre constante.
 */

/**
 * Retourne la liste des INSERT de données de référence, dans l'ordre de
 * dépendance (mêmes contraintes FK que ueb_get_table_schemas()).
 *
 * @return array<string, string> table => SQL
 */
function ueb_get_seed_data() {
    return array(
        'ueb_regions' => <<<SQL
INSERT IGNORE INTO ueb_regions (code, nom) VALUES ('10', 'ADAMAOUA'), ('11', 'CENTRE'), ('12', 'EST'), ('13', 'EXTREME-NORD'), ('14', 'LITTORAL'), ('15', 'NORD'), ('16', 'NORD-OUEST'), ('17', 'OUEST'), ('18', 'SUD'), ('19', 'SUD-OUEST');
SQL,
        'ueb_departements' => <<<SQL
INSERT IGNORE INTO ueb_departements (code, nom, region_id) VALUES
    ('1001', 'DJEREM', (SELECT id FROM ueb_regions WHERE code = '10')),
    ('1002', 'FARO ET DEO', (SELECT id FROM ueb_regions WHERE code = '10')),
    ('1003', 'MAYO-BANYO', (SELECT id FROM ueb_regions WHERE code = '10')),
    ('1004', 'MBERE', (SELECT id FROM ueb_regions WHERE code = '10')),
    ('1005', 'VINA', (SELECT id FROM ueb_regions WHERE code = '10')),
    ('1101', 'HAUTE SANAGA', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1102', 'LEKIE', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1103', 'MBAM ET INOUBOU', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1104', 'MBAM ET KIM', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1105', 'MEFOU ET AFAMBA', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1106', 'MEFOU ET AKONO', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1107', 'MFOUNDI', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1108', 'NYONG ET KELLE', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1109', 'NYONG ET MFOUMOU', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1110', 'NYONG ET SO''O', (SELECT id FROM ueb_regions WHERE code = '11')),
    ('1201', 'BOUMBA ET NGOKO', (SELECT id FROM ueb_regions WHERE code = '12')),
    ('1202', 'HAUT NYONG', (SELECT id FROM ueb_regions WHERE code = '12')),
    ('1203', 'KADEY', (SELECT id FROM ueb_regions WHERE code = '12')),
    ('1204', 'LOM ET DJEREM', (SELECT id FROM ueb_regions WHERE code = '12')),
    ('1301', 'DIAMARE', (SELECT id FROM ueb_regions WHERE code = '13')),
    ('1302', 'LOGONE ET CHARI', (SELECT id FROM ueb_regions WHERE code = '13')),
    ('1303', 'MAYO DANAY', (SELECT id FROM ueb_regions WHERE code = '13')),
    ('1304', 'MAYO KANI', (SELECT id FROM ueb_regions WHERE code = '13')),
    ('1305', 'MAYO SAVA', (SELECT id FROM ueb_regions WHERE code = '13')),
    ('1306', 'MAYO TSANAGA', (SELECT id FROM ueb_regions WHERE code = '13')),
    ('1401', 'MOUNGO', (SELECT id FROM ueb_regions WHERE code = '14')),
    ('1402', 'NKAM', (SELECT id FROM ueb_regions WHERE code = '14')),
    ('1403', 'SANAGA MARITIME', (SELECT id FROM ueb_regions WHERE code = '14')),
    ('1404', 'WOURI', (SELECT id FROM ueb_regions WHERE code = '14')),
    ('1501', 'BENOUE', (SELECT id FROM ueb_regions WHERE code = '15')),
    ('1502', 'FARO', (SELECT id FROM ueb_regions WHERE code = '15')),
    ('1503', 'MAYO LOUTI', (SELECT id FROM ueb_regions WHERE code = '15')),
    ('1504', 'MAYO REY', (SELECT id FROM ueb_regions WHERE code = '15')),
    ('1601', 'BOYO', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1602', 'BUI', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1603', 'DONGA MANTUNG', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1604', 'MENCHUM', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1605', 'MEZAM', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1606', 'MOMO', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1607', 'NGO KETUNDJIA', (SELECT id FROM ueb_regions WHERE code = '16')),
    ('1701', 'BAMBOUTOS', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1702', 'HAUT-NKAM', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1703', 'HAUTS-PLATEAUX', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1704', 'KOUNG-KHI', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1705', 'MENOUA', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1706', 'MIFI', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1707', 'NDE', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1708', 'NOUN', (SELECT id FROM ueb_regions WHERE code = '17')),
    ('1801', 'DJA ET LOBO', (SELECT id FROM ueb_regions WHERE code = '18')),
    ('1802', 'MVILA', (SELECT id FROM ueb_regions WHERE code = '18')),
    ('1803', 'OCEAN', (SELECT id FROM ueb_regions WHERE code = '18')),
    ('1804', 'VALLEE DU NTEM', (SELECT id FROM ueb_regions WHERE code = '18')),
    ('1901', 'FAKO', (SELECT id FROM ueb_regions WHERE code = '19')),
    ('1902', 'KOUPE ET MANENGOUBA', (SELECT id FROM ueb_regions WHERE code = '19')),
    ('1903', 'LEBIALEM', (SELECT id FROM ueb_regions WHERE code = '19')),
    ('1904', 'MANYU', (SELECT id FROM ueb_regions WHERE code = '19')),
    ('1905', 'MEME', (SELECT id FROM ueb_regions WHERE code = '19')),
    ('1906', 'NDIAN', (SELECT id FROM ueb_regions WHERE code = '19'));
SQL,
        'ueb_communes' => <<<SQL
INSERT IGNORE INTO ueb_communes (code, nom, departement_id) VALUES
    ('100101', 'NGAOUNDAL', (SELECT id FROM ueb_departements WHERE code = '1001')),
    ('100102', 'TIBATI', (SELECT id FROM ueb_departements WHERE code = '1001')),
    ('100201', 'GALIM TIGNERE', (SELECT id FROM ueb_departements WHERE code = '1002')),
    ('100202', 'MAYO BALEO', (SELECT id FROM ueb_departements WHERE code = '1002')),
    ('100203', 'KONTCHA', (SELECT id FROM ueb_departements WHERE code = '1002')),
    ('100204', 'TIGNERE', (SELECT id FROM ueb_departements WHERE code = '1002')),
    ('100301', 'BANKIM', (SELECT id FROM ueb_departements WHERE code = '1003')),
    ('100302', 'BANYO', (SELECT id FROM ueb_departements WHERE code = '1003')),
    ('100303', 'MAYO-DARLE', (SELECT id FROM ueb_departements WHERE code = '1003')),
    ('100401', 'DIR', (SELECT id FROM ueb_departements WHERE code = '1004')),
    ('100402', 'DJOHONG', (SELECT id FROM ueb_departements WHERE code = '1004')),
    ('100403', 'NGAOUI', (SELECT id FROM ueb_departements WHERE code = '1004')),
    ('100404', 'MEIGANGA', (SELECT id FROM ueb_departements WHERE code = '1004')),
    ('100500', 'CU DE NGAOUNDERE', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100501', 'BELEL', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100502', 'MBE', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100503', 'NGAOUNDÉRÉ I', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100504', 'NGAOUNDÉRÉ II', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100505', 'NGAOUNDÉRÉ III', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100506', 'MARTAP', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100507', 'NGAN-HA', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('100508', 'NYAMBAKA', (SELECT id FROM ueb_departements WHERE code = '1005')),
    ('110101', 'MBANDJOCK', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110102', 'MINTA', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110103', 'NANGA-EBOKO', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110104', 'BIBEY', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110105', 'LEMBE YEZOUM', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110106', 'NSEM', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110107', 'NKOTENG', (SELECT id FROM ueb_departements WHERE code = '1101')),
    ('110201', 'EBEBDA', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110202', 'ELIG-MFOMO', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110203', 'EVODOULA', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110204', 'MONATELE', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110205', 'OBALA', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110206', 'BATCHENGA', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110207', 'OKOLA', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110208', 'LOBO', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110209', 'SA''A', (SELECT id FROM ueb_departements WHERE code = '1102')),
    ('110301', 'BAFIA', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110302', 'BOKITO', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110303', 'DEUK', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110304', 'MAKENENE', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110305', 'NDIKINIMEKI', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110306', 'NITOUKOU', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110307', 'OMBESSA', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110308', 'KIIKI', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110309', 'KON-YAMBETTA', (SELECT id FROM ueb_departements WHERE code = '1103')),
    ('110401', 'MBANGASSINA', (SELECT id FROM ueb_departements WHERE code = '1104')),
    ('110402', 'NGAMBE-TIKAR', (SELECT id FROM ueb_departements WHERE code = '1104')),
    ('110403', 'NGORO', (SELECT id FROM ueb_departements WHERE code = '1104')),
    ('110404', 'NTUI', (SELECT id FROM ueb_departements WHERE code = '1104')),
    ('110405', 'YOKO', (SELECT id FROM ueb_departements WHERE code = '1104')),
    ('110501', 'AWAE', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110502', 'OLANGUINA / ASSAMBA', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110503', 'ESSE', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110504', 'AFANLOUM', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110505', 'EDZENDOUAN', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110506', 'MFOU', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110507', 'NKOLAFAMBA', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110508', 'SOA', (SELECT id FROM ueb_departements WHERE code = '1105')),
    ('110601', 'AKONO', (SELECT id FROM ueb_departements WHERE code = '1106')),
    ('110602', 'BIKOK', (SELECT id FROM ueb_departements WHERE code = '1106')),
    ('110603', 'MBANKOMO', (SELECT id FROM ueb_departements WHERE code = '1106')),
    ('110604', 'NGOUMOU', (SELECT id FROM ueb_departements WHERE code = '1106')),
    ('110700', 'CU DE YAOUNDE', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110701', 'YAOUNDE 1', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110702', 'YAOUNDE 2', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110703', 'YAOUNDE 3', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110704', 'YAOUNDE 4', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110705', 'YAOUNDE 5', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110706', 'YAOUNDE 6', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110707', 'YAOUNDE 7', (SELECT id FROM ueb_departements WHERE code = '1107')),
    ('110801', 'BOT MAKAK', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110802', 'NGUIBASSAL', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110803', 'DIBANG', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110804', 'ESEKA', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110805', 'MAKAK', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110806', 'BONDJOCK', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110807', 'MATOMB', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110808', 'MESSONDO', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110809', 'BIYOUHA', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110810', 'NGOG MAPUBI', (SELECT id FROM ueb_departements WHERE code = '1108')),
    ('110901', 'AKONOLINGA', (SELECT id FROM ueb_departements WHERE code = '1109')),
    ('110902', 'MENGANG', (SELECT id FROM ueb_departements WHERE code = '1109')),
    ('110903', 'AYOS', (SELECT id FROM ueb_departements WHERE code = '1109')),
    ('110904', 'KOBDOMBO / NYAKOKOMBO', (SELECT id FROM ueb_departements WHERE code = '1109')),
    ('110905', 'ENDOM', (SELECT id FROM ueb_departements WHERE code = '1109')),
    ('111001', 'DZENG', (SELECT id FROM ueb_departements WHERE code = '1110')),
    ('111002', 'MBALMAYO', (SELECT id FROM ueb_departements WHERE code = '1110')),
    ('111003', 'AKOEMAN', (SELECT id FROM ueb_departements WHERE code = '1110')),
    ('111004', 'MENGUEME', (SELECT id FROM ueb_departements WHERE code = '1110')),
    ('111005', 'NKOLMETET', (SELECT id FROM ueb_departements WHERE code = '1110')),
    ('111006', 'NGOMEDZAP', (SELECT id FROM ueb_departements WHERE code = '1110')),
    ('120101', 'GARI GOMBO', (SELECT id FROM ueb_departements WHERE code = '1201')),
    ('120102', 'MOLOUNDOU', (SELECT id FROM ueb_departements WHERE code = '1201')),
    ('120103', 'SALAPOUMBE', (SELECT id FROM ueb_departements WHERE code = '1201')),
    ('120104', 'YOKADOUMA', (SELECT id FROM ueb_departements WHERE code = '1201')),
    ('120201', 'ABONG - MBANG', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120202', 'ATOK / BEBEND', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120203', 'MINDOUROU / DJA', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120204', 'ANGOSSAS / MBOANZ', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120205', 'DIMAKO', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120206', 'DOUME', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120207', 'DOUMAINTANG', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120208', 'LOMIE', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120209', 'MESSOK', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120210', 'MESSAMENA', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120211', 'SOMALOMO', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120212', 'NGOYLA', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120213', 'NGUELEMENDOUKA', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120214', 'MBOMA', (SELECT id FROM ueb_departements WHERE code = '1202')),
    ('120301', 'BATOURI', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120302', 'NGUELEBOK / NDEM-NAM', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120303', 'KETTE', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120304', 'OULI / MBOTORO', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120305', 'MBANG', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120306', 'NDELELE', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120307', 'KENTZOU / BOMBE', (SELECT id FROM ueb_departements WHERE code = '1203')),
    ('120400', 'CU DE BERTOUA', (SELECT id FROM ueb_departements WHERE code = '1201')),
    ('120401', 'BELABO', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120402', 'BETARE-OYA', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120403', 'NGOURA', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120404', 'BERTOUA 1ER', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120405', 'DIANG', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120406', 'GAROUA-BOULAI', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120407', 'BERTOUA IIE', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('120408', 'MANDJOU', (SELECT id FROM ueb_departements WHERE code = '1204')),
    ('130100', 'CU DE MAROUA', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130101', 'BOGO', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130102', 'GAZAWA', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130103', 'MAROUA I', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130104', 'DARGALA', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130105', 'NDOUKOULA', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130106', 'MERI', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130107', 'PETTE', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130108', 'MAROUA II', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130109', 'MAROUA III', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130201', 'BLANGOUA', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130202', 'DARAK', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130203', 'FOTOKOL', (SELECT id FROM ueb_departements WHERE code = '1301')),
    ('130204', 'GOULFEY', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130205', 'HILE-ALIFA', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130206', 'KOUSSERI', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130207', 'LOGONE BIRNI', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130208', 'ZINA', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130209', 'MAKARI', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130210', 'WAZA', (SELECT id FROM ueb_departements WHERE code = '1302')),
    ('130301', 'DATCHEKA', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130302', 'GOBO', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130303', 'GUERE', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130304', 'KAÏ-KAÏ', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130305', 'KALFOU', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130306', 'KARHAY', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130307', 'MAGA', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130308', 'TCHATIBALI', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130309', 'GUEME / VELE', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130310', 'WINA', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130311', 'YAGOUA', (SELECT id FROM ueb_departements WHERE code = '1303')),
    ('130401', 'GUIDIGUIS', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130402', 'KAELE', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130403', 'MINDIF', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130404', 'MOULVOUDAYE', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130405', 'MOUTOURWA', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130406', 'PORHI', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130407', 'DZIGUILAO / TAIBONG', (SELECT id FROM ueb_departements WHERE code = '1304')),
    ('130501', 'KOLOFATA', (SELECT id FROM ueb_departements WHERE code = '1305')),
    ('130502', 'MORA', (SELECT id FROM ueb_departements WHERE code = '1305')),
    ('130503', 'TOKOMBERE', (SELECT id FROM ueb_departements WHERE code = '1305')),
    ('130601', 'BOURRHA', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('130602', 'HINA', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('130603', 'KOZA', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('130604', 'MAYO-MOSKOTA', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('130605', 'MOGODE', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('130606', 'MOKOLO', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('130607', 'SOULEDE ROUA', (SELECT id FROM ueb_departements WHERE code = '1306')),
    ('140100', 'CU DE NKONGSAMBA', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140101', 'BARE-BAKEM', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140102', 'DIBOMBARI', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140103', 'ABO/FIKO', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140104', 'LOUM', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140105', 'MANJO', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140106', 'MBANGA', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140107', 'MOMBO', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140108', 'MELONG', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140109', 'NJOMBE-PENJA', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140110', 'NKONGSAMBA 1', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140111', 'EBONE / NLONAKO', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140112', 'NKONGSAMBA 2', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140113', 'NKONGSAMBA 3', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140201', 'NKONDJOCK', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140202', 'NDOBIAN / NORD MAKOMBE', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140203', 'YABASSI', (SELECT id FROM ueb_departements WHERE code = '1402')),
    ('140204', 'YINGUI', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140300', 'CU DE EDEA', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140301', 'DIZANGUE', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140302', 'EDEA 1', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140303', 'MOUANKO', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140304', 'NDOM', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140305', 'NYANON', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140306', 'NGAMBE', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140307', 'MASSOK-SONGLOULOU', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140308', 'POUMA', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140309', 'DIBAMBA', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140310', 'EDEA 2', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140311', 'NGWEI', (SELECT id FROM ueb_departements WHERE code = '1403')),
    ('140400', 'CU DE DOUALA', (SELECT id FROM ueb_departements WHERE code = '1401')),
    ('140401', 'DOUALA 1', (SELECT id FROM ueb_departements WHERE code = '1404')),
    ('140402', 'DOUALA 2', (SELECT id FROM ueb_departements WHERE code = '1404')),
    ('140403', 'DOUALA 3', (SELECT id FROM ueb_departements WHERE code = '1404')),
    ('140404', 'DOUALA 4', (SELECT id FROM ueb_departements WHERE code = '1404')),
    ('140405', 'DOUALA 5', (SELECT id FROM ueb_departements WHERE code = '1404')),
    ('140406', 'DOUALA 6', (SELECT id FROM ueb_departements WHERE code = '1404')),
    ('150100', 'CU DE GAROUA', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150101', 'BIBEMI', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150102', 'DEMBO', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150103', 'LAGDO', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150104', 'GAROUA I', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150105', 'BASCHEO', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150106', 'GASHIGA / DEMSA', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150107', 'TOUROUA', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150108', 'PITOA', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150109', 'TCHEBOA', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150110', 'GAROUA II', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150111', 'GAROUA III', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150112', 'MAYO HOURNA', (SELECT id FROM ueb_departements WHERE code = '1501')),
    ('150201', 'BEKA', (SELECT id FROM ueb_departements WHERE code = '1502')),
    ('150202', 'POLI', (SELECT id FROM ueb_departements WHERE code = '1502')),
    ('150301', 'FIGUIL', (SELECT id FROM ueb_departements WHERE code = '1503')),
    ('150302', 'GUIDER', (SELECT id FROM ueb_departements WHERE code = '1503')),
    ('150303', 'MAYO OULO', (SELECT id FROM ueb_departements WHERE code = '1503')),
    ('150401', 'REY-BOUBA', (SELECT id FROM ueb_departements WHERE code = '1504')),
    ('150402', 'TCHOLLIRE', (SELECT id FROM ueb_departements WHERE code = '1504')),
    ('150403', 'MADINGRING', (SELECT id FROM ueb_departements WHERE code = '1504')),
    ('150404', 'TOUBORO', (SELECT id FROM ueb_departements WHERE code = '1504')),
    ('160101', 'BELO', (SELECT id FROM ueb_departements WHERE code = '1601')),
    ('160102', 'FONFUKA / BUM', (SELECT id FROM ueb_departements WHERE code = '1601')),
    ('160103', 'FUNDONG', (SELECT id FROM ueb_departements WHERE code = '1601')),
    ('160104', 'NJINIKOM', (SELECT id FROM ueb_departements WHERE code = '1601')),
    ('160201', 'JAKIRI', (SELECT id FROM ueb_departements WHERE code = '1602')),
    ('160202', 'KUMBO', (SELECT id FROM ueb_departements WHERE code = '1602')),
    ('160203', 'MBIAME / MBVEN', (SELECT id FROM ueb_departements WHERE code = '1602')),
    ('160204', 'NKOR / NONI', (SELECT id FROM ueb_departements WHERE code = '1602')),
    ('160205', 'ELAK OKU / OKU', (SELECT id FROM ueb_departements WHERE code = '1602')),
    ('160206', 'NKUM', (SELECT id FROM ueb_departements WHERE code = '1602')),
    ('160301', 'AKO', (SELECT id FROM ueb_departements WHERE code = '1603')),
    ('160302', 'MISAJE', (SELECT id FROM ueb_departements WHERE code = '1603')),
    ('160303', 'NDU', (SELECT id FROM ueb_departements WHERE code = '1603')),
    ('160304', 'NKAMBE', (SELECT id FROM ueb_departements WHERE code = '1603')),
    ('160305', 'NWA', (SELECT id FROM ueb_departements WHERE code = '1603')),
    ('160401', 'ZHOA / FUNGOM', (SELECT id FROM ueb_departements WHERE code = '1604')),
    ('160402', 'FURU-AWA', (SELECT id FROM ueb_departements WHERE code = '1604')),
    ('160403', 'BENAKUMA / MENCHUM VALLEY', (SELECT id FROM ueb_departements WHERE code = '1604')),
    ('160404', 'WUM / WUM CENTRAL', (SELECT id FROM ueb_departements WHERE code = '1604')),
    ('160500', 'CU DE BAMENDA', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160501', 'BAFUT', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160502', 'BALI', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160503', 'BAMENDA 1', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160504', 'SANTA', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160505', 'TUBAH', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160506', 'BAMENDA 2', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160507', 'BAMENDA 3', (SELECT id FROM ueb_departements WHERE code = '1605')),
    ('160601', 'BATIBO', (SELECT id FROM ueb_departements WHERE code = '1606')),
    ('160602', 'MBENGWI', (SELECT id FROM ueb_departements WHERE code = '1606')),
    ('160603', 'ANDEK / NGIE', (SELECT id FROM ueb_departements WHERE code = '1606')),
    ('160604', 'NJIKWA', (SELECT id FROM ueb_departements WHERE code = '1606')),
    ('160605', 'WIDIKUM-MENKA', (SELECT id FROM ueb_departements WHERE code = '1606')),
    ('160701', 'BABESSI', (SELECT id FROM ueb_departements WHERE code = '1607')),
    ('160702', 'BALIKUMBAT', (SELECT id FROM ueb_departements WHERE code = '1607')),
    ('160703', 'NDOP', (SELECT id FROM ueb_departements WHERE code = '1607')),
    ('170101', 'BABADJOU', (SELECT id FROM ueb_departements WHERE code = '1701')),
    ('170102', 'BATCHAM', (SELECT id FROM ueb_departements WHERE code = '1701')),
    ('170103', 'GALIM', (SELECT id FROM ueb_departements WHERE code = '1701')),
    ('170104', 'MBOUDA', (SELECT id FROM ueb_departements WHERE code = '1701')),
    ('170201', 'BAFANG', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170202', 'BAKOU', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170203', 'BANA', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170204', 'BANDJA', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170205', 'KEKEM', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170206', 'BANWA', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170207', 'BANKA', (SELECT id FROM ueb_departements WHERE code = '1702')),
    ('170301', 'BAHAM', (SELECT id FROM ueb_departements WHERE code = '1703')),
    ('170302', 'BAMENDJOU', (SELECT id FROM ueb_departements WHERE code = '1703')),
    ('170303', 'BATIE', (SELECT id FROM ueb_departements WHERE code = '1703')),
    ('170304', 'BANGOU', (SELECT id FROM ueb_departements WHERE code = '1703')),
    ('170401', 'BAYANGAM', (SELECT id FROM ueb_departements WHERE code = '1704')),
    ('170402', 'PETE BANDJOUN / POUMOUGNE', (SELECT id FROM ueb_departements WHERE code = '1704')),
    ('170403', 'DEMDING / DJEBEM', (SELECT id FROM ueb_departements WHERE code = '1704')),
    ('170501', 'DSCHANG', (SELECT id FROM ueb_departements WHERE code = '1705')),
    ('170502', 'FOKOUE', (SELECT id FROM ueb_departements WHERE code = '1705')),
    ('170503', 'NKONG-ZEM / NKONG-NI', (SELECT id FROM ueb_departements WHERE code = '1705')),
    ('170504', 'PENKA MICHEL', (SELECT id FROM ueb_departements WHERE code = '1705')),
    ('170505', 'SANTCHOU', (SELECT id FROM ueb_departements WHERE code = '1705')),
    ('170506', 'FONGO TONGO', (SELECT id FROM ueb_departements WHERE code = '1705')),
    ('170600', 'CU DE BAFFOUSSAM', (SELECT id FROM ueb_departements WHERE code = '1706')),
    ('170601', 'BAFOUSSAM 1', (SELECT id FROM ueb_departements WHERE code = '1706')),
    ('170602', 'BAFOUSSAM 2', (SELECT id FROM ueb_departements WHERE code = '1706')),
    ('170603', 'BAFOUSSAM 3', (SELECT id FROM ueb_departements WHERE code = '1706')),
    ('170701', 'BANGANGTE', (SELECT id FROM ueb_departements WHERE code = '1707')),
    ('170702', 'BASSAMBA', (SELECT id FROM ueb_departements WHERE code = '1707')),
    ('170703', 'BAZOU', (SELECT id FROM ueb_departements WHERE code = '1707')),
    ('170704', 'TONGA', (SELECT id FROM ueb_departements WHERE code = '1707')),
    ('170801', 'BANGOURAIN', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170802', 'FOUMBAN', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170803', 'FOUMBOT', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170804', 'KOUOPTAMO', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170805', 'KOUTABA', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170806', 'MAGBA', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170807', 'MALENTOUEN', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170808', 'MASSANGAM', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('170809', 'NJIMOM', (SELECT id FROM ueb_departements WHERE code = '1708')),
    ('180101', 'BENGBIS', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180102', 'DJOUM', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180103', 'MEYOMESSALA', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180104', 'MINTOM', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180105', 'OVENG', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180106', 'SANGMELIMA', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180107', 'ZOETELE', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180108', 'MEYOMESSI', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180200', 'CU DE EBOLOWA', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180201', 'BIWONG-BANE', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180202', 'EBOLOWA IER', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180203', 'MENGONG', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180204', 'MVANGAN', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180205', 'NGOULEMAKONG', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180206', 'BIWONG-BULU', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180207', 'EBOLOWA IIE', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180208', 'EFOULAN', (SELECT id FROM ueb_departements WHERE code = '1802')),
    ('180300', 'CU DE KRIBI', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180301', 'AKOM 2', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180302', 'NIETE', (SELECT id FROM ueb_departements WHERE code = '1801')),
    ('180303', 'BIPINDI', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180304', 'CAMPO', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180305', 'KRIBI 1', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180306', 'LOLODORF', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180307', 'MVENGUE', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180308', 'KRIBI 2', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180309', 'LOKOUNDJE', (SELECT id FROM ueb_departements WHERE code = '1803')),
    ('180401', 'AMBAM', (SELECT id FROM ueb_departements WHERE code = '1804')),
    ('180402', 'MA''AN', (SELECT id FROM ueb_departements WHERE code = '1804')),
    ('180403', 'OLAMZE', (SELECT id FROM ueb_departements WHERE code = '1804')),
    ('180404', 'KYE OSSI', (SELECT id FROM ueb_departements WHERE code = '1804')),
    ('190100', 'CU DE LIMBE', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190101', 'BUEA', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190102', 'IDENAU / WEST COAST', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190103', 'LIMBÉ 1', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190104', 'MUYUKA', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190105', 'TIKO', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190106', 'LIMBÉ 2', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190107', 'LIMBÉ 3', (SELECT id FROM ueb_departements WHERE code = '1901')),
    ('190201', 'BANGEM', (SELECT id FROM ueb_departements WHERE code = '1902')),
    ('190202', 'NGUTI', (SELECT id FROM ueb_departements WHERE code = '1902')),
    ('190203', 'TOMBEL', (SELECT id FROM ueb_departements WHERE code = '1902')),
    ('190301', 'ALOU', (SELECT id FROM ueb_departements WHERE code = '1903')),
    ('190302', 'MENJI / FONTEM', (SELECT id FROM ueb_departements WHERE code = '1903')),
    ('190303', 'WABANE', (SELECT id FROM ueb_departements WHERE code = '1903')),
    ('190401', 'AKWAYA', (SELECT id FROM ueb_departements WHERE code = '1904')),
    ('190402', 'EYUMODJOCK', (SELECT id FROM ueb_departements WHERE code = '1904')),
    ('190403', 'MAMFE CENTRAL', (SELECT id FROM ueb_departements WHERE code = '1904')),
    ('190404', 'TIMTO / UPPER BANYANG', (SELECT id FROM ueb_departements WHERE code = '1904')),
    ('190500', 'CU DE KUMBA', (SELECT id FROM ueb_departements WHERE code = '1905')),
    ('190501', 'KUMBA 1', (SELECT id FROM ueb_departements WHERE code = '1905')),
    ('190502', 'KONYE', (SELECT id FROM ueb_departements WHERE code = '1905')),
    ('190503', 'MBONGE', (SELECT id FROM ueb_departements WHERE code = '1905')),
    ('190504', 'KUMBA 2', (SELECT id FROM ueb_departements WHERE code = '1905')),
    ('190505', 'KUMBA 3', (SELECT id FROM ueb_departements WHERE code = '1905')),
    ('190601', 'BAMUSSO', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190602', 'EKONDO-TITI', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190603', 'DIKOME BALUE', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190604', 'IDABATO', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190605', 'ISANGELE', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190606', 'KOMBO ABEDIMO', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190607', 'KOMBO ITINDI', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190608', 'MUNDEMBA', (SELECT id FROM ueb_departements WHERE code = '1906')),
    ('190609', 'TOKO', (SELECT id FROM ueb_departements WHERE code = '1906'));
SQL,
        'ueb_facultes' => <<<SQL
INSERT IGNORE INTO ueb_facultes (code, nom_fr, nom_en, logo) VALUES
    ('FS', 'Faculté des Sciences', 'Faculty of Science', 'logo-fs.jpg'),
    ('FALSH', 'Faculté des Arts, Lettres et Sciences Humaines', 'Faculty of Arts, Letters and Human Sciences', 'logo-falsh.jpg'),
    ('FSEG', 'Faculté des Sciences Économiques et de Gestion', 'Faculty of Economics and Management', 'logo-fseg.jpg'),
    ('FSJP', 'Faculté des Sciences Juridiques et Politiques', 'Faculty of Law and Political Sciences', 'logo-fsjp.jpg');
SQL,
        'ueb_diplomes_admission' => <<<SQL
INSERT IGNORE INTO ueb_diplomes_admission (code, libelle) VALUES
    ('bac', 'Baccalauréat'),
    ('gce_ol', 'GCE O-Level');
SQL,
        'ueb_specialites_diplome' => <<<SQL
INSERT IGNORE INTO ueb_specialites_diplome (code, libelle, faculte_id, diplome_id) VALUES
    -- ===== FS — Bac =====
    ('C_FS',        'Série C — Mathématiques et Sciences Physiques',     (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('D_FS',        'Série D — Sciences Naturelles',                     (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('E_FS',        'Série E — Mathématiques et Technique',              (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('F_FS',        'Série F — Sciences Techniques',                     (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('G_FS',        'Série G — Techniques de Gestion',                  (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('TI_FS',       'Série TI — Technologies de l''Information',        (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    -- ===== FS — GCE O/L =====
    ('GCE_OL_SCI',  'GCE O/L — Sciences',                               (SELECT id FROM ueb_facultes WHERE code = 'FS'),    (SELECT id FROM ueb_diplomes_admission WHERE code = 'gce_ol')),
    -- ===== FALSH — Bac =====
    ('A_FALSH',     'Série A — Lettres, Philosophie, Sciences Sociales', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('B_FALSH',     'Série B — Sciences Économiques et Sociales',        (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('C_FALSH',     'Série C — Mathématiques et Sciences Physiques',     (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('D_FALSH',     'Série D — Sciences Naturelles',                     (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('G_FALSH',     'Série G — Techniques de Gestion',                  (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('TI_FALSH',    'Série TI — Technologies de l''Information',        (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    -- ===== FALSH — GCE O/L =====
    ('GCE_OL_ART',  'GCE O/L — Arts & Humanities',                      (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'gce_ol')),
    ('GCE_OL_SOC',  'GCE O/L — Social Sciences',                        (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'gce_ol')),
    -- ===== FSEG — Bac =====
    ('A_FSEG',      'Série A — Lettres, Philosophie, Sciences Sociales', (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('B_FSEG',      'Série B — Sciences Économiques et Sociales',        (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('C_FSEG',      'Série C — Mathématiques et Sciences Physiques',     (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('D_FSEG',      'Série D — Sciences Naturelles',                     (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('G_FSEG',      'Série G — Techniques de Gestion',                  (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('TI_FSEG',     'Série TI — Technologies de l''Information',        (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    -- ===== FSEG — GCE O/L =====
    ('GCE_OL_COM',  'GCE O/L — Commerce / Economics',                   (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'gce_ol')),
    ('GCE_OL_GEN',  'GCE O/L — General',                                (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'gce_ol')),
    -- ===== FSJP — Bac =====
    ('A_FSJP',      'Série A — Lettres, Philosophie, Sciences Sociales', (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('B_FSJP',      'Série B — Sciences Économiques et Sociales',        (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('C_FSJP',      'Série C — Mathématiques et Sciences Physiques',     (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('D_FSJP',      'Série D — Sciences Naturelles',                     (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('G_FSJP',      'Série G — Techniques de Gestion',                  (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    ('TI_FSJP',     'Série TI — Technologies de l''Information',        (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'bac')),
    -- ===== FSJP — GCE O/L =====
    ('GCE_OL_ALL',  'GCE O/L — Toutes séries',                          (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), (SELECT id FROM ueb_diplomes_admission WHERE code = 'gce_ol'));
SQL,
        'ueb_filieres' => <<<SQL
INSERT IGNORE INTO ueb_filieres (code, libelle, faculte_id, type_formation) VALUES
    ('TIC', 'TIC — Technologies de l''Information et de la Communication', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'classique'),
    ('PHY', 'Physique Appliquée', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'classique'),
    ('CHIM', 'Chimie Appliquée', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'classique'),
    ('GEO', 'Géosciences et Environnement', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'classique'),
    ('ROSE', 'ROSE — Recherche Opérationnelle et Économétrie', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'classique'),
    ('BIO', 'Biotechnologie et Pharmacognosie', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'classique'),
    ('LP_BIO_MED', 'LP Sciences Biomédicales et Médico-Sanitaires', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'pro'),
    ('LP_BIO_AGR', 'LP Sciences Biologiques Appliquées à l''Agriculture', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'pro'),
    ('LP_TBM', 'LP Technologie Bio-Médicale', (SELECT id FROM ueb_facultes WHERE code = 'FS'), 'pro'),
    ('LMF', 'Lettres Modernes Françaises', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), 'classique'),
    ('LEA', 'Langues Étrangères Appliquées', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), 'classique'),
    ('HIST', 'Histoire', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), 'classique'),
    ('GEO_FALSH', 'Géographie', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), 'classique'),
    ('PHILO', 'Philosophie', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), 'classique'),
    ('SOCIO', 'Sociologie', (SELECT id FROM ueb_facultes WHERE code = 'FALSH'), 'classique'),
    ('ECO', 'Économie', (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), 'classique'),
    ('GEST', 'Gestion', (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), 'classique'),
    ('COMPTA', 'Comptabilité et Finance', (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), 'classique'),
    ('BANQUE', 'Banque et Finance', (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), 'classique'),
    ('MKT', 'Marketing', (SELECT id FROM ueb_facultes WHERE code = 'FSEG'), 'classique'),
    ('DPRIV', 'Droit Privé', (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), 'classique'),
    ('DPUB', 'Droit Public', (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), 'classique'),
    ('SCPOL', 'Science Politique', (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), 'classique'),
    ('RI', 'Relations Internationales', (SELECT id FROM ueb_facultes WHERE code = 'FSJP'), 'classique');
SQL,
        'ueb_situations_matrimoniales' => <<<SQL
INSERT IGNORE INTO ueb_situations_matrimoniales (code, libelle) VALUES
    ('celibataire', 'Célibataire'),
    ('marie', 'Marié(e)'),
    ('divorce', 'Divorcé(e)'),
    ('veuf', 'Veuf / Veuve');
SQL,
        'ueb_statuts_socio_professionnels' => <<<SQL
INSERT IGNORE INTO ueb_statuts_socio_professionnels (libelle) VALUES
    ('Élève / Étudiant(e)'),
    ('Sans emploi'),
    ('Salarié(e) du secteur public'),
    ('Salarié(e) du secteur privé'),
    ('Commerçant(e) / Indépendant(e)'),
    ('Agriculteur / Éleveur'),
    ('Fonctionnaire'),
    ('Retraité(e)'),
    ('Autre');
SQL,
        'ueb_nationalites' => <<<SQL
INSERT IGNORE INTO ueb_nationalites (nom) VALUES
    ('Camerounaise'),
    ('Tchadienne'),
    ('Nigériane'),
    ('Centrafricaine'),
    ('Congolaise'),
    ('Gabonaise'),
    ('Équato-Guinéenne'),
    ('Française'),
    ('Ivoirienne'),
    ('Sénégalaise'),
    ('Béninoise'),
    ('Togolaise'),
    ('Malienne'),
    ('Guinéenne'),
    ('Autre');
SQL,
        'ueb_niveaux_lmd' => <<<SQL
INSERT IGNORE INTO ueb_niveaux_lmd (code, libelle, ordre) VALUES
    ('L1', 'Licence 1', 1), ('L2', 'Licence 2', 2), ('L3', 'Licence 3', 3),
    ('M1', 'Master 1', 4), ('M2', 'Master 2', 5), ('DOC', 'Doctorat', 6);
SQL,
        'ueb_mentions' => <<<SQL
INSERT IGNORE INTO ueb_mentions (code, libelle, ordre) VALUES
    ('passable', 'Passable', 1), ('assez_bien', 'Assez Bien', 2), ('bien', 'Bien', 3),
    ('tres_bien', 'Très Bien', 4), ('excellent', 'Excellent', 5);
SQL,
        'ueb_statuts_etudiants' => <<<SQL
INSERT IGNORE INTO ueb_statuts_etudiants (code, libelle) VALUES
    ('cemac', 'Étudiant CEMAC'), ('hors_cemac', 'Étudiant Hors CEMAC');
SQL,
        'ueb_langues' => <<<SQL
INSERT IGNORE INTO ueb_langues (code, libelle) VALUES
    ('fr', 'Français'), ('en', 'Anglais');
SQL,
        'ueb_sports' => <<<SQL
INSERT IGNORE INTO ueb_sports (libelle) VALUES
    ('Football'), ('Basketball'), ('Handball'), ('Volleyball'),
    ('Athlétisme'), ('Natation'), ('Tennis'), ('Judo / Arts martiaux'), ('Autre');
SQL,
        'ueb_arts' => <<<SQL
INSERT IGNORE INTO ueb_arts (libelle) VALUES
    ('Musique'), ('Théâtre'), ('Danse'), ('Chant'), ('Peinture / Dessin'), ('Cinéma'), ('Autre');
SQL,
    );
}

/**
 * Insère les données de référence dans toutes les tables ueb_* de type
 * lookup. Utilise INSERT IGNORE : sûr à ré-exécuter (cf. note en haut
 * du fichier).
 */
function ueb_seed_reference_data() {
    global $wpdb;

    foreach ( ueb_get_seed_data() as $table => $sql ) {
        $result = $wpdb->query( $sql );

        if ( false === $result ) {
            error_log( sprintf(
                '[UEB DB] Échec du seed de données pour la table "%s" : %s',
                $table,
                $wpdb->last_error
            ) );
        }
    }
}

/**
 * Vérifie si les données de référence ont déjà été insérées pour la
 * version courante du schéma (UEB_DB_SCHEMA_VERSION, définie dans
 * db-schema.php). Si non, lance ueb_seed_reference_data().
 *
 * Accroché aux mêmes hooks que ueb_maybe_upgrade_db() (db-schema.php),
 * et exécuté APRÈS elle (l'ordre des require_once dans functions.php
 * doit charger db-schema.php avant db-seed.php pour que les tables
 * existent déjà au moment du seed).
 */
function ueb_maybe_seed_data() {
    $version_seedee = get_option( 'ueb_data_version' );

    if ( $version_seedee === UEB_DB_SCHEMA_VERSION ) {
        return; 
    }

    ueb_seed_reference_data();
    update_option( 'ueb_data_version', UEB_DB_SCHEMA_VERSION );
}
add_action( 'after_switch_theme', 'ueb_maybe_seed_data', 20 ); 
add_action( 'admin_init', 'ueb_maybe_seed_data', 20 );