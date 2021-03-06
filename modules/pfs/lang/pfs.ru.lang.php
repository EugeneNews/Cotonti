<?php
/**
 * Russian Language File for the PFS Module (pfs.ru.lang.php)
 *
 * @package pfs
 * @version 0.7.0
 * @author Cotonti Translators Team
 * @copyright Copyright (c) Cotonti Team 2008-2011
 * @license BSD
 */

defined('COT_CODE') or die('Wrong URL.');

/**
 * Module Config
 */

$L['cfg_maxpfsperpage'] = array('Макс. количество элементов на странице', ' ');
$L['cfg_pfsfilecheck'] = array('Проверка файлов', 'Проверять загружаемые файлы (&laquo;'.$L['PFS'].'&raquo; и профиль) на соответствие их формата используемому расширению. Рекомендуется включить в целях безопасности.');
$L['cfg_pfsmaxuploads'] = array('Макс. число параллельных закачек за раз', '');
$L['cfg_pfsnomimepass'] = array('Игнорировать MIME-типы', 'Разрешить закачку файлов, MIME-тип которых не указан в конфигурации.');
$L['cfg_pfstimename'] = array('Имена файлов на основе шаблона времени', 'Генерировать имена файлов по шаблону времени. По умолчанию используется маска ИМЯФАЙЛА_UDERID.');
$L['cfg_pfsuserfolder'] = array('Режим хранения по каталогам', 'Пользовательские файлы будут храниться в каталогах /datas/users/USERID/ вместо /datas/users/ и добавления USERID к имени файла. Устанавливается <u>только при начальной настройке сайта</u>. Менять значение после первой загрузки любого файла не рекомендуется!');
$L['cfg_flashupload'] = array('Использовать flash-загрузку файлов', 'Позволяет одновременно загружать несколько файлов.'); // New in 1.0.0
$L['cfg_pfs_winclose'] = array('Закрывать всплывающее окно после вставки ббкода');
$L['cfg_th_amode'] = array('Метод создания миниатюр изображений (thumbnails)', ' ');
$L['cfg_th_border'] = array('Ширина рамки миниатюры, px', 'По умолчанию: 4px');
$L['cfg_th_colorbg'] = array('Цвет рамки миниатюры', 'По умолчанию: #000000');
$L['cfg_th_colortext'] = array('Цвет текста миниатюры', 'По умолчанию: #FFFFFF');
$L['cfg_th_dimpriority'] = array('Приоритет размеров миниатюр (thumbnails)', ' ');
$L['cfg_th_jpeg_quality'] = array('Коэффициент JPEG-сжатия миниатюры', 'По умолчанию: 85');
$L['cfg_th_keepratio'] = array('Сохранять пропорции изображения в миниатюре', ' ');
$L['cfg_th_separator'] = 'Настройки миниатюр';
$L['cfg_th_textsize'] = array('Размер шрифта миниатюры', ' ');
$L['cfg_th_x'] = array('Ширина миниатюры, px', 'По умолчанию: 112px');
$L['cfg_th_y'] = array('Высота миниатюры, px', 'По умолчанию: 84px (рекомендуется: ширина x 0.75)');

/**
 * Other
 */

$L['adm_gd'] = 'Графическая библиотека GD';
$L['adm_allpfs'] = 'Разделы &laquo;'.$L['PFS'].'&raquo; всех пользователей';
$L['adm_allfiles'] = 'Все файлы';
$L['adm_thumbnails'] = 'Миниатюры';
$L['adm_orphandbentries'] = 'Потерянные записи БД';
$L['adm_orphanfiles'] = 'Потерянные файлы';
$L['adm_delallthumbs'] = 'Удалить все миниатюры';
$L['adm_rebuildallthumbs']= 'Удалить и сгенерировать все миниатюры';
$L['adm_help_allpfs'] = 'Разделы &laquo;'.$L['PFS'].'&raquo; всех зарегистрированных пользователей';
$L['adm_nogd'] = 'Графическая библиотека GD не поддерживается данным хостом. Создание миниатюр для изображений невозможно. Установите переменную &laquo;Метод создания миниатюр&raquo; ('.$L['Configuration'].' &gt; '.$L['PFS'].') в значение &laquo;'.$L['Disabled'].'&raquo;';
$L['adm_help_pfsfiles'] = 'Недоступно';
$L['adm_help_pfsthumbs'] = 'Недоступно';
$L['info_desc'] = 'Организованное хранилище для загружаемых файлов, предоставляемое зарегистрированным пользоватлям (Личное файловое пространство) и для всего сайта (Файловое пространство сайта)';

/**
 * Main
 */

$L['pfs_cancelall'] = 'Отменить все';
$L['pfs_direxists'] = 'Такой каталог уже существует.<br />Старый путь: %1$s<br />Новый путь: %2$s';
$L['pfs_extallowed'] = 'Разрешенные расширения';
$L['pfs_filecheckfail'] = 'Внимание: ошибка расширения файла 2$s.%1$s';
$L['pfs_filechecknomime'] = 'Внимание: не найден MIME-тип для файла 2$s.%1$s';
$L['pfs_fileexists'] = 'Ошибка загрузки: файл с таким именем уже существует';
$L['pfs_filelistempty'] = 'Список пуст';
$L['pfs_filemimemissing'] = 'Ошибка загрузки: отсутствует MIME-тип для расширения %1$s';
$L['pfs_filenotmoved'] = 'Ошибка загрузки: временный файл не может быть перемещен.';
$L['pfs_filenotvalid'] = 'Ошибка проверки %1$s-файла';
$L['pfs_filesintheroot'] = 'Файлов в корневом каталоге';
$L['pfs_filesinthisfolder'] = 'Файлов в текущем каталоге';
$L['pfs_filetoobigorext'] = 'Ошибка загрузки: файл слишком велик или недопустимое расширение';
$L['pfs_folderistempty'] = 'Каталог пуст';
$L['pfs_foldertitlemissing'] = 'Отсутствует заголовок каталога';
$L['pfs_isgallery'] = 'Галерея?';
$L['pfs_ispublic'] = 'Открытый доступ?';
$L['pfs_maxsize'] = 'Максимальный размер файла';
$L['pfs_maxspace'] = 'Максимальный разрешенный объем';
$L['pfs_newfile'] = 'Загрузить файл';
$L['pfs_newfolder'] = 'Создать новый каталог';
$L['pfs_onpage'] = 'На этой странице';
$L['pfs_parentfolder'] = 'Родительский каталог';
$L['pfs_pastefile'] = 'Вставить как ссылку на файл';
$L['pfs_pasteimage'] = 'Вставить как изображение';
$L['pfs_pastethumb'] = 'Вставить миниатюру';
$L['pfs_resizeimages'] = 'Масштабировать изображение?';
$L['pfs_title'] = 'Мои файлы';
$L['pfs_totalsize'] = 'Общий объем';
$L['pfs_uploadfiles'] = 'Загрузить файлы';

$L['pfs_insertasthumbnail'] = 'Вставить миниатюру';
$L['pfs_insertasimage'] = 'Вставить полноразмерное изображение';
$L['pfs_insertaslink'] = 'Вставить в виде ссылки на файл';
$L['pfs_dimensions'] = 'Размеры';

$L['pfs_confirm_delete_file'] = 'Вы действительно хотите удалить этот файл?';
$L['pfs_confirm_delete_file'] = 'Вы действительно хотите удалить эту папку и всё её содержимое?';

?>