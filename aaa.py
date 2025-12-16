nums=[1,2,3,4,5,6]
even=[n for n in nums if n%2==0]
odd=[n for n in nums if n%2!=0]
print(even[-1],odd[0])