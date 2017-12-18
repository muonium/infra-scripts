#!/usr/bin/php
<?php

//Add DB config
try {
    $db = new PDO('mysql:host=localhost;dbname=cloud', 'root', '');
} catch (PDOException $e) {
    print "Error ! : " . $e->getMessage() . "<br/>";
    die();
}

if($argc != 3 && $argc != 4) {
    printHelp();
} else {
    switch ($argv[1]) {
        case "-u":
        case "--username":
            if($argc == 4) {
                if($argv[3] == "--verbose" || $argv[3] == "-v") {  
                    deleteByUsername($argv[2], true);
                } else {
                    deleteByUsername($argv[2], false);
                }
            } else {
                    deleteByUsername($argv[2], false);
            }
            break;
        case "-i":
        case "--id":
            if($argc == 4) {
                if($argv[3] == "--verbose" || $argv[3] == "-v") {  
                    deleteById($argv[2], true);
                } else {
                    deleteById($argv[2], false);
                }
            } else {
                    deleteById($argv[2], false);
            }
            break;
        case "-e":
        case "--email":
            if($argc == 4) {
                if($argv[3] == "--verbose" || $argv[3] == "-v") {  
                    deleteByEmail($argv[2], true);
                } else {
                    deleteByEmail($argv[2], false);
                }
            } else {
                    deleteByEmail($argv[2], false);
            }
            break;
        default:
            printHelp();
            break;
    } 
}

$db = null;

function getIDbyUsername($anUsername) {
    $theRequest = $db->prepare('SELECT id FROM users WHERE login = :login');
    $theRequest->bindParam(':login', $anUsername, PDO::PARAM_STR);
    $theRequest->execute();
    $id = $theRequest->fetch();
    return $id;
}

function getIDbyEmail($anEmail) {
    $theRequest = $db->prepare('SELECT id FROM users WHERE email = :email');
    $theRequest->bindParam(':email', $anEmail, PDO::PARAM_STR);
    $theRequest->execute();
    $id = $theRequest->fetch();
    return $id;
}

function getMailFromID($anID) {
    $theRequest = $db->prepare('SELECT email FROM users WHERE id = :id');
    $theRequest->bindParam(':id', $anID, PDO::PARAM_INT);
    $theRequest->execute();
    $mail = $theRequest->fetch();
    return $mail;
}

function getUsernameFromID($anID) {
    $theRequest = $db->prepare('SELECT login FROM users WHERE id = :id');
    $theRequest->bindParam(':id', $anID, PDO::PARAM_INT);
    $theRequest->execute();
    $username = $theRequest->fetch();
    return $username;
}

function deleteByUsername($anUsername, $isVerbose) {
    $id = getIDbyUsername($anUsername);
    deleteByID($id, $isVerbose);
}

function deleteByEmail($anEmail) {
    $id = getIDbyEmail($anEmail);
    deleteByID($id, $isVerbose);
}

function deleteByID($anID) {
    $mail = getMailFromID($anID);
    $username = getUsernameFromID($anID);
    $theRequest = $db->prepare("DELETE FROM ban,
                                            files,
                                            folders,
                                            storage,
                                            upgrade,
                                            users,
                                            user_lostpass,
                                            user_validation
                                WHERE
                                    users.id = ban.id_user
                                AND
                                    users.id = files.id_owner
                                AND
                                    users.id = folders.id_owner
                                AND
                                    users.id = storage.id_user
                                AND
                                    users.id = upgrade.id_user
                                AND
                                    users.id = user_lostpass.id_user
                                AND
                                    users.id = user_validation.id_user
                                AND 
                                    users.id = :id");
    $theRequest->bindParam(':id', $anID, PDO::PARAM_INT);
    $theRequest->execute();
    if($theRequest->rowCount() != 0)
        if($isVerbose) {
            echo 'ID : '.$anID.', Mail : '.$mail.', Username : '.$username.' deleted';
        } else {
            echo 'User deleted.';
            
        }
    else
        echo 'No user deleted';
}

function printHelp() {
    ?>
    Usage :
    <?php echo $argv[0]; ?> <option> <argument>

    <option> can be one of the 3 followings options to delete an user
    -u <argument> or --username <argument> to delete an user using username
    -i <argument> or --id <argument> to delete an user using id
    -e <argument> or --email <argument> to delete an user using email
    
    You can add -v or --verbose to print details
    <?php
}

?>