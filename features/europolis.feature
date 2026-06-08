Feature: Europolis API Testing

  Scenario: Create a new game
    Given API-ul este pornit
    When trimit o cerere POST la "/games" cu date valide
    Then raspunsul trebuie sa aiba codul 201
    And jocul trebuie sa fie creat

  Scenario: Add a player
    Given exista un joc creat
    When trimit o cerere POST la "/games/1/players"
    Then jucatorul trebuie sa fie adaugat in joc

  Scenario: Execute a turn
    Given exista un joc cu jucatori
    When trimit o cerere POST la "/games/1/turns"
    Then pozitia jucatorului trebuie sa se actualizeze

  Scenario: Create a trade
    Given exista doi jucatori in joc
    When trimit o cerere POST la "/games/1/trades"
    Then schimbul trebuie sa fie inregistrat

  Scenario: Zero money does not finish the game too early
    Given exista un joc activ cu doi jucatori pentru final
    When al doilea jucator ramane cu zero bani
    Then jocul trebuie sa ramana activ

  Scenario: Finish game when only one active player remains
    Given exista un joc activ cu doi jucatori pentru final
    When al doilea jucator pierde jocul
    Then primul jucator trebuie sa fie castigator
